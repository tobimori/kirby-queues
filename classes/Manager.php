<?php

namespace tobimori\Queues;

use Kirby\Cms\App;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Toolkit\Str;
use tobimori\Queues\Schedule\Scheduler;
use tobimori\Queues\Storage\CacheStorage;
use tobimori\Queues\Storage\StorageInterface;

/**
 * Queue manager
 *
 * Central manager for queue operations, coordinating between
 * storage, scheduler, and worker components.
 */
class Manager
{
	/**
	 * @var StorageInterface Storage implementation
	 */
	protected StorageInterface $storage;

	/**
	 * @var Scheduler Schedule manager
	 */
	protected Scheduler $scheduler;

	/**
	 * @var App Kirby instance
	 */
	protected App $kirby;

	/**
	 * Constructor
	 */
	public function __construct(?StorageInterface $storage = null)
	{
		$this->kirby = App::instance();
		$this->storage = $storage ?? new CacheStorage();
		$this->scheduler = new Scheduler($this);
	}

	/**
	 * Push a job to the queue
	 *
	 * @throws InvalidArgumentException
	 */
	public function push(string|Job $job, array $payload = [], ?string $queue = null): string
	{
		$job = $this->prepareJob($job, $payload);
		$jobId = $this->generateJobId();
		$job->setId($jobId);

		// determine queue
		$queue = $queue ?? $job->options()['queue'] ?? $this->defaultQueue();

		// prepare job data for storage
		$jobData = array_merge($job->toArray(), [
			'id' => $jobId,
			'queue' => $queue,
			'attempts' => 0,  // Will be incremented to 1 when job starts
			'available_at' => time()
		]);

		// store job
		$this->storage->push($queue, $jobData);

		return $jobId;
	}

	/**
	 * Push a job with delay
	 *
	 * @throws InvalidArgumentException
	 */
	public function later(int $delay, string|Job $job, array $payload = [], ?string $queue = null): string
	{
		$job = $this->prepareJob($job, $payload);
		$jobId = $this->generateJobId();
		$job->setId($jobId);

		// determine queue
		$queue = $queue ?? $job->options()['queue'] ?? $this->defaultQueue();

		// prepare job data for storage
		$jobData = array_merge($job->toArray(), [
			'id' => $jobId,
			'queue' => $queue,
			'attempts' => 0,
			'available_at' => time() + $delay
		]);

		// store job
		$this->storage->push($queue, $jobData);

		return $jobId;
	}

	/**
	 * Get the next job from a queue
	 */
	public function pop(?string $queue = null): ?Job
	{
		$queue = $queue ?? $this->defaultQueue();
		$jobData = $this->storage->pop($queue);

		if ($jobData === null) {
			return null;
		}

		return Job::fromArray($jobData);
	}

	/**
	 * Get job by ID
	 */
	public function find(string $id): ?Job
	{
		$jobData = $this->storage->get($id);

		if ($jobData === null) {
			return null;
		}

		return Job::fromArray($jobData);
	}

	/**
	 * Delete a job
	 */
	public function delete(string $id): void
	{
		$this->storage->delete($id);
	}


	/**
	 * Add a log entry to a job
	 *
	 * @internal
	 */
	public function addJobLog(string $id, string $level, string $message, array $context = []): void
	{
		$job = $this->storage->get($id);
		if ($job === null) {
			return;
		}

		$logs = $job['logs'] ?? [];
		$logs[] = [
			'timestamp' => time(),
			'level' => $level,
			'message' => $message,
			'context' => $context
		];

		// Keep only last 100 log entries
		if (count($logs) > 100) {
			$logs = array_slice($logs, -100);
		}

		$this->storage->update($id, ['logs' => $logs]);
	}

	/**
	 * Mark job as running
	 *
	 * @internal
	 */
	public function markRunning(string $id, string $workerId): void
	{
		$this->storage->markRunning($id, $workerId);
	}

	/**
	 * Mark job as completed
	 *
	 * @internal
	 */
	public function markCompleted(string $id, mixed $result = null): void
	{
		$this->storage->markCompleted($id, $result);
	}

	/**
	 * Mark job as failed
	 *
	 * @internal
	 */
	public function markFailed(string $id, \Exception $exception): void
	{
		$this->storage->markFailed($id, $exception->getMessage(), [
			'class' => get_class($exception),
			'message' => $exception->getMessage(),
			'code' => $exception->getCode(),
			'file' => $exception->getFile(),
			'line' => $exception->getLine(),
			'trace' => $exception->getTraceAsString()
		]);
	}

	/**
	 * Release job back to queue for retry
	 *
	 * @internal
	 */
	public function release(string $id, int $delay = 0): void
	{
		$this->storage->release($id, $delay);
	}
	
	/**
	 * Update job attempts count
	 *
	 * @internal
	 */
	public function updateJobAttempts(string $id, int $attempts): void
	{
		$this->storage->update($id, ['attempts' => $attempts]);
	}

	/**
	 * Retry a failed job
	 *
	 * @throws InvalidArgumentException If job not found or not failed
	 */
	public function retry(string $id): string
	{
		$job = $this->find($id);

		if ($job === null) {
			throw new InvalidArgumentException("Job with ID '{$id}' not found");
		}

		$jobData = $this->storage->get($id);

		if ($jobData['status'] !== JobStatus::FAILED->value) {
			throw new InvalidArgumentException("Only failed jobs can be retried");
		}

		// create new job with same data
		return $this->push($job->type(), $job->payload(), $jobData['queue']);
	}

	/**
	 * Get jobs by status
	 */
	public function getByStatus(JobStatus|string $status, int $limit = 100, int $offset = 0): array
	{
		$status = is_string($status) ? $status : $status->value;
		return $this->storage->getByStatus($status, $limit, $offset);
	}

	/**
	 * Get queue statistics
	 */
	public function stats(): array
	{
		return $this->storage->stats();
	}

	/**
	 * Get all queues with job counts
	 */
	public function queues(): array
	{
		return $this->storage->queues();
	}

	/**
	 * Clear old jobs
	 */
	public function clear(?int $completedHours = null, ?int $failedHours = null): int
	{
		$completedHours = $completedHours ?? $this->kirby->option('tobimori.queues.retention.completed', 24);
		$failedHours = $failedHours ?? $this->kirby->option('tobimori.queues.retention.failed', 168);

		return $this->storage->clear($completedHours, $failedHours);
	}

	/**
	 * Get scheduler instance
	 */
	public function scheduler(): Scheduler
	{
		return $this->scheduler;
	}

	/**
	 * Get storage instance
	 */
	public function storage(): StorageInterface
	{
		return $this->storage;
	}

	/**
	 * Get default queue name
	 */
	protected function defaultQueue(): string
	{
		return $this->kirby->option('tobimori.queues.default', 'default');
	}

	/**
	 * Prepare job instance from string or Job object
	 */
	protected function prepareJob(string|Job $job, array $payload): Job
	{
		// create job instance if string provided
		if (is_string($job)) {
			// check if it's a class name or job type
			if (class_exists($job) && is_subclass_of($job, Job::class)) {
				// it's a class name, create instance directly
				$instance = new $job();
				$instance->setPayload($payload);
				
				// ensure the job type is registered
				$type = $instance->type();
				if (Queues::job($type) === null) {
					Queues::register($job);
				}
				
				$job = $instance;
			} else {
				// it's a job type, use the registry
				$job = Queues::createJob($job, $payload);
			}
		} else {
			$job->setPayload($payload);
		}
		
		return $job;
	}

	/**
	 * Generate unique job ID
	 */
	protected function generateJobId(): string
	{
		return Str::uuid();
	}
}