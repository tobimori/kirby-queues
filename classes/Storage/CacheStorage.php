<?php

namespace tobimori\Queues\Storage;

use Kirby\Cache\Cache;
use Kirby\Cms\App;
use tobimori\Queues\JobStatus;

/**
 * Cache-based storage implementation
 *
 * Uses Kirby's cache system to store queue jobs. This implementation
 * works with any Kirby cache driver (file, redis, memcached, etc.)
 *
 * @package tobimori\Queues\Storage
 */
class CacheStorage implements StorageInterface
{
	/**
	 * @var Cache Kirby cache instance
	 */
	protected Cache $cache;

	/**
	 * @var string Cache key prefix
	 */
	protected string $prefix = 'queues';

	/**
	 * Constructor
	 *
	 * @param Cache|null $cache Cache instance (defaults to 'queues' cache)
	 */
	public function __construct(?Cache $cache = null)
	{
		$this->cache = $cache ?? App::instance()->cache('tobimori.queues');
	}

	/**
	 * @inheritDoc
	 *
	 * @param array<string, mixed> $job Job data
	 */
	public function push(string $queue, array $job): void
	{
		// ensure job has required fields - defaults first, then job data overwrites
		$defaults = [
			'queue' => $queue,
			'status' => JobStatus::PENDING->value,
			'created_at' => time(),
			'available_at' => time()
		];

		// Only set defaults if not already present in job data
		foreach ($defaults as $key => $value) {
			if (!isset($job[$key])) {
				$job[$key] = $value;
			}
		}

		// store job data
		$jobKey = $this->jobKey($job['id']);
		$this->cache->set($jobKey, $job);
		$this->trackKey($jobKey);

		// add to queue index
		$this->addToQueueIndex($queue, $job['id']);
	}

	/**
	 * @inheritDoc
	 *
	 * @return array<string, mixed>|null Job data or null if no job available
	 */
	public function pop(string $queue): ?array
	{
		$now = time();
		$queueJobs = $this->getQueueIndex($queue);

		foreach ($queueJobs as $jobId) {
			$job = $this->get($jobId);

			if ($job === null) {
				// job was deleted, remove from index
				$this->removeFromQueueIndex($queue, $jobId);
				continue;
			}

			// check if job is available and pending
			if ($job['status'] === JobStatus::PENDING->value && $job['available_at'] <= $now) {
				// remove from queue index immediately to prevent race conditions
				$this->removeFromQueueIndex($queue, $jobId);
				return $job;
			}
		}

		return null;
	}

	/**
	 * @inheritDoc
	 *
	 * @return array<string, mixed>|null Job data or null if not found
	 */
	public function get(string $id): ?array
	{
		return $this->cache->get($this->jobKey($id));
	}

	/**
	 * @inheritDoc
	 *
	 * @param array<string, mixed> $data Job data to update
	 */
	public function update(string $id, array $data): void
	{
		$job = $this->get($id);

		if ($job !== null) {
			$job = array_merge($job, $data, ['updated_at' => time()]);
			$jobKey = $this->jobKey($id);
			$this->cache->set($jobKey, $job);
			$this->trackKey($jobKey);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function delete(string $id): void
	{
		$job = $this->get($id);

		if ($job !== null) {
			// remove from queue index if pending
			if ($job['status'] === JobStatus::PENDING->value && isset($job['queue'])) {
				$this->removeFromQueueIndex($job['queue'], $id);
			}

			$this->cache->remove($this->jobKey($id));
		}
	}

	/**
	 * @inheritDoc
	 *
	 * @return array<array<string, mixed>> Array of job data
	 */
	public function getByStatus(string $status, int $limit = 100, int $offset = 0): array
	{
		$jobs = [];
		$count = 0;
		$skipped = 0;

		// get all job keys
		$pattern = $this->prefix . '.job.*';
		$keys = $this->getAllKeys($pattern);

		foreach ($keys as $key) {
			$job = $this->cache->get($key);

			if ($job !== null && $job['status'] === $status) {
				if ($skipped < $offset) {
					$skipped++;
					continue;
				}

				$jobs[] = $job;
				$count++;

				if ($count >= $limit) {
					break;
				}
			}
		}

		// sort by created_at descending
		usort($jobs, fn ($a, $b) => $b['created_at'] <=> $a['created_at']);

		return $jobs;
	}

	/**
	 * @inheritDoc
	 *
	 * @return array<string, mixed> Statistics data
	 */
	public function stats(): array
	{
		$stats = [
			'total' => 0,
			'by_status' => [
				JobStatus::PENDING->value => 0,
				JobStatus::RUNNING->value => 0,
				JobStatus::COMPLETED->value => 0,
				JobStatus::FAILED->value => 0
			],
			'by_queue' => []
		];

		$pattern = $this->prefix . '.job.*';
		$keys = $this->getAllKeys($pattern);

		foreach ($keys as $key) {
			$job = $this->cache->get($key);

			if ($job !== null) {
				$stats['total']++;
				$stats['by_status'][$job['status']]++;

				$queue = $job['queue'] ?? 'default';
				if (!isset($stats['by_queue'][$queue])) {
					$stats['by_queue'][$queue] = 0;
				}
				$stats['by_queue'][$queue]++;
			}
		}

		return $stats;
	}

	/**
	 * @inheritDoc
	 *
	 * @return array<string, int> Queue names mapped to job counts
	 */
	public function queues(): array
	{
		$queues = [];
		$pattern = $this->prefix . '.queue.*';
		$keys = $this->getAllKeys($pattern);

		foreach ($keys as $key) {
			// extract queue name from key
			$queueName = str_replace($this->prefix . '.queue.', '', $key);
			$jobs = $this->getQueueIndex($queueName);
			$queues[$queueName] = count($jobs);
		}

		return $queues;
	}

	/**
	 * @inheritDoc
	 */
	public function clear(int $completedHours = 24, int $failedHours = 168): int
	{
		$cleared = 0;
		$now = time();
		$completedCutoff = $now - ($completedHours * 3600);
		$failedCutoff = $now - ($failedHours * 3600);

		$pattern = $this->prefix . '.job.*';
		$keys = $this->getAllKeys($pattern);

		foreach ($keys as $key) {
			$job = $this->cache->get($key);

			if ($job === null) {
				continue;
			}

			$shouldClear = false;

			if ($job['status'] === JobStatus::COMPLETED->value && $job['completed_at'] < $completedCutoff) {
				$shouldClear = true;
			} elseif ($job['status'] === JobStatus::FAILED->value && $job['failed_at'] < $failedCutoff) {
				$shouldClear = true;
			}

			if ($shouldClear) {
				$this->cache->remove($key);
				$cleared++;
			}
		}

		return $cleared;
	}

	/**
	 * @inheritDoc
	 *
	 * @return array<string, mixed> Scheduled jobs data
	 */
	public function getScheduled(): array
	{
		return $this->cache->get($this->scheduledKey()) ?? [];
	}

	/**
	 * @inheritDoc
	 *
	 * @param array<string, mixed> $scheduled Scheduled jobs data
	 */
	public function saveScheduled(array $scheduled): void
	{
		$this->cache->set($this->scheduledKey(), $scheduled);
	}

	/**
	 * @inheritDoc
	 */
	public function markRunning(string $id, string $workerId): void
	{
		$job = $this->get($id);
		if ($job !== null) {
			// Increment attempts when job starts running
			$attempts = ($job['attempts'] ?? 0) + 1;

			$this->update($id, [
				'status' => JobStatus::RUNNING->value,
				'started_at' => time(),
				'worker_id' => $workerId,
				'attempts' => $attempts
			]);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function markCompleted(string $id, mixed $result = null): void
	{
		$this->update($id, [
			'status' => JobStatus::COMPLETED->value,
			'completed_at' => time(),
			'result' => $result
		]);
	}

	/**
	 * @inheritDoc
	 *
	 * @param array<string, mixed> $exception Exception data
	 */
	public function markFailed(string $id, string $error, array $exception = []): void
	{
		$this->update($id, [
			'status' => JobStatus::FAILED->value,
			'failed_at' => time(),
			'error' => $error,
			'exception' => $exception
		]);
	}

	/**
	 * @inheritDoc
	 */
	public function release(string $id, int $delay = 0): void
	{
		$job = $this->get($id);

		if ($job !== null) {
			$this->update($id, [
				'status' => JobStatus::PENDING->value,
				'available_at' => time() + $delay,
				'worker_id' => null
			]);

			// add back to queue index
			$this->addToQueueIndex($job['queue'], $id);
		}
	}

	/**
	 * Get cache key for job
	 *
	 * @param string $id Job ID
	 * @return string
	 */
	protected function jobKey(string $id): string
	{
		return $this->prefix . '.job.' . $id;
	}

	/**
	 * Get cache key for queue index
	 *
	 * @param string $queue Queue name
	 * @return string
	 */
	protected function queueKey(string $queue): string
	{
		return $this->prefix . '.queue.' . $queue;
	}

	/**
	 * Get cache key for scheduled jobs
	 *
	 * @return string
	 */
	protected function scheduledKey(): string
	{
		return $this->prefix . '.scheduled';
	}

	/**
	 * Get queue index (list of job IDs)
	 *
	 * @param string $queue Queue name
	 * @return array<string> List of job IDs
	 */
	protected function getQueueIndex(string $queue): array
	{
		return $this->cache->get($this->queueKey($queue)) ?? [];
	}

	/**
	 * Add job to queue index
	 *
	 * @param string $queue Queue name
	 * @param string $jobId Job ID
	 * @return void
	 */
	protected function addToQueueIndex(string $queue, string $jobId): void
	{
		$jobs = $this->getQueueIndex($queue);
		$jobs[] = $jobId;
		$this->cache->set($this->queueKey($queue), array_unique($jobs));
	}

	/**
	 * Remove job from queue index
	 *
	 * @param string $queue Queue name
	 * @param string $jobId Job ID
	 * @return void
	 */
	protected function removeFromQueueIndex(string $queue, string $jobId): void
	{
		$jobs = $this->getQueueIndex($queue);
		$jobs = array_diff($jobs, [$jobId]);
		$this->cache->set($this->queueKey($queue), array_values($jobs));
	}

	/**
	 * Get all cache keys matching pattern
	 *
	 * Note: This is a workaround since Kirby cache doesn't provide key listing.
	 * For file cache, we scan the directory. For other drivers, this may not work.
	 *
	 * @param string $pattern Key pattern
	 * @return array<string> List of cache keys
	 */
	protected function getAllKeys(string $pattern): array
	{
		// this is a simplified implementation
		// in production, you might need driver-specific implementations
		$keys = [];

		// for now, we'll track keys separately
		$indexKey = $this->prefix . '.index';
		$index = $this->cache->get($indexKey) ?? [];

		foreach ($index as $key) {
			if (fnmatch($pattern, $key)) {
				$keys[] = $key;
			}
		}

		return $keys;
	}

	/**
	 * Track a cache key in the index
	 *
	 * @param string $key Cache key
	 * @return void
	 */
	protected function trackKey(string $key): void
	{
		$indexKey = $this->prefix . '.index';
		$index = $this->cache->get($indexKey) ?? [];
		$index[] = $key;
		$this->cache->set($indexKey, array_unique($index));
	}
}
