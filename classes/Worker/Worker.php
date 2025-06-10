<?php

namespace tobimori\Queues\Worker;

use Kirby\CLI\CLI;
use Kirby\Cms\App;
use Kirby\Toolkit\Str;
use tobimori\Queues\Job;
use tobimori\Queues\JobResult;
use tobimori\Queues\JobStatus;
use tobimori\Queues\Manager;
use tobimori\Queues\Queues;

/**
 * Queue worker
 *
 * Processes jobs from the queue and handles scheduled tasks.
 * The worker is responsible for fetching jobs, executing them,
 * and handling failures/retries.
 */
class Worker
{
	/**
	 * @var Manager Queue manager
	 */
	protected Manager $manager;

	/**
	 * @var CLI|null CLI instance for output
	 */
	protected ?CLI $cli;

	/**
	 * @var string Worker ID
	 */
	protected string $workerId;

	/**
	 * @var array Worker options
	 */
	protected array $options;

	/**
	 * @var bool Whether worker should stop
	 */
	protected bool $shouldStop = false;

	/**
	 * @var int Jobs processed counter
	 */
	protected int $jobsProcessed = 0;

	/**
	 * @var float Worker start time
	 */
	protected float $startTime;

	/**
	 * Constructor
	 */
	public function __construct(?Manager $manager = null, ?CLI $cli = null, array $options = [])
	{
		$this->manager = $manager ?? Queues::manager();
		$this->cli = $cli;
		$this->workerId = $this->generateWorkerId();
		$this->startTime = microtime(true);

		// merge with default options
		$defaults = App::instance()->option('tobimori.queues.worker', [
			'timeout' => 60,
			'memory' => 128,
			'sleep' => 5,
			'tries' => 3,
			'backoff' => 60,
			'maxJobs' => 1000
		]);
		$this->options = array_merge($defaults, $options);

		// register signal handlers for graceful shutdown
		if (function_exists('pcntl_signal')) {
			pcntl_signal(SIGTERM, [$this, 'stop']);
			pcntl_signal(SIGINT, [$this, 'stop']);
		}
	}

	/**
	 * Start the worker loop
	 */
	public function work(string|array|null $queues = null, bool $once = false): void
	{
		$queues = $this->normalizeQueues($queues);

		$this->output("Worker {$this->workerId} started", 'info');
		$this->output("Processing queues: " . implode(', ', $queues), 'info');

		while (!$this->shouldStop) {
			// check scheduled jobs every loop iteration
			$this->runScheduledJobs();

			// process jobs from each queue
			$processed = false;

			foreach ($queues as $queue) {
				$job = $this->getNextJob($queue);

				if ($job !== null) {
					$this->processJob($job);
					$processed = true;
					$this->jobsProcessed++;

					// check if we should restart
					if ($this->shouldRestart()) {
						$this->output("Restarting after {$this->jobsProcessed} jobs (maxJobs: " . ($this->options['maxJobs'] ?? 'not set') . ")", 'info');
						$this->stop();
						break;
					}

					// exit if only processing once
					if ($once) {
						return;
					}
				}
			}

			// if no jobs were processed, sleep before next check
			if (!$processed) {
				$this->sleep();
			}

			// check for async signals
			if (function_exists('pcntl_signal_dispatch')) {
				pcntl_signal_dispatch();
			}
		}

		$this->output("Worker {$this->workerId} stopped", 'info');
	}

	/**
	 * Process a single job
	 */
	public function processJob(Job $job): JobResult
	{
		$startTime = microtime(true);

		$this->output("Processing job {$job->id()} [{$job->type()}]", 'info');

		// mark job as running (this increments attempts in storage)
		$this->manager->markRunning($job->id(), $this->workerId);

		try {
			// set up timeout if configured
			$timeout = $job->timeout();
			if ($timeout > 0 && function_exists('pcntl_alarm')) {
				pcntl_signal(SIGALRM, function () use ($job) {
					throw new \RuntimeException("Job {$job->id()} timed out after {$job->timeout()} seconds");
				});
				pcntl_alarm($timeout);
			}

			// execute the job
			$job->handle();

			// clear timeout
			if ($timeout > 0 && function_exists('pcntl_alarm')) {
				pcntl_alarm(0);
			}

			// mark as completed
			$this->manager->markCompleted($job->id());

			$result = new JobResult(
				$job->id(),
				JobStatus::COMPLETED,
				$startTime,
				microtime(true)
			);

			$this->output("Job {$job->id()} completed in {$result->durationMs()}ms", 'success');

			return $result;

		} catch (\Exception $e) {
			// clear timeout
			if (isset($timeout) && $timeout > 0 && function_exists('pcntl_alarm')) {
				pcntl_alarm(0);
			}

			return $this->handleFailedJob($job, $e, $startTime);
		}
	}

	/**
	 * Handle a failed job
	 */
	protected function handleFailedJob(Job $job, \Exception $exception, float $startTime): JobResult
	{
		$this->output("Job {$job->id()} failed: " . $exception->getMessage(), 'error');

		// check if job should be retried
		if ($job->shouldRetry()) {
			$delay = $job->retryBackoff();
			
			$this->manager->release($job->id(), $delay);

			$this->output("Job {$job->id()} will be retried in {$delay} seconds (attempt {$job->attempts()}/{$job->maxAttempts()})", 'warning');

			return new JobResult(
				$job->id(),
				JobStatus::PENDING,
				$startTime,
				microtime(true),
				null,
				$exception
			);
		}

		// mark as failed
		$this->manager->markFailed($job->id(), $exception);

		// call job's failed method
		try {
			$job->failed($exception);
		} catch (\Exception $e) {
			$this->output("Job {$job->id()} failed handler threw exception: " . $e->getMessage(), 'error');
		}

		return new JobResult(
			$job->id(),
			JobStatus::FAILED,
			$startTime,
			microtime(true),
			null,
			$exception
		);
	}

	/**
	 * Run scheduled jobs
	 */
	protected function runScheduledJobs(): void
	{
		try {
			$queued = $this->manager->scheduler()->runDue();

			if (!empty($queued)) {
				$this->output("Queued " . count($queued) . " scheduled job(s)", 'info');
			}
		} catch (\Exception $e) {
			$this->output("Failed to run scheduled jobs: " . $e->getMessage(), 'error');
		}
	}

	/**
	 * Get next job from queue
	 */
	protected function getNextJob(string $queue): ?Job
	{
		try {
			return $this->manager->pop($queue);
		} catch (\Exception $e) {
			$this->output("Failed to fetch job from queue {$queue}: " . $e->getMessage(), 'error');
			return null;
		}
	}

	/**
	 * Normalize queue names
	 */
	protected function normalizeQueues(string|array|null $queues): array
	{
		if ($queues === null) {
			// When no queues specified, use all registered queues
			return Queues::registeredQueues();
		}

		return is_string($queues) ? [$queues] : $queues;
	}

	/**
	 * Check if worker should restart
	 */
	protected function shouldRestart(): bool
	{
		// restart after max jobs processed
		$maxJobs = $this->options['maxJobs'] ?? 1000;
		// Ensure maxJobs is at least 1 and not 0
		if ($maxJobs <= 0) {
			$maxJobs = 1000;
		}
		
		if ($this->jobsProcessed >= $maxJobs) {
			$this->output("Max jobs reached ({$this->jobsProcessed}/{$maxJobs})", 'info');
			return true;
		}

		// restart if memory limit exceeded
		$memoryLimit = ($this->options['memory'] ?? 128) * 1024 * 1024;
		if (memory_get_usage(true) >= $memoryLimit) {
			$this->output("Memory limit exceeded ({$this->options['memory']}MB)", 'warning');
			return true;
		}

		return false;
	}

	/**
	 * Sleep between queue checks
	 */
	protected function sleep(): void
	{
		sleep($this->options['sleep']);
	}

	/**
	 * Stop the worker
	 */
	public function stop(): void
	{
		$this->shouldStop = true;
	}

	/**
	 * Generate unique worker ID
	 */
	protected function generateWorkerId(): string
	{
		return gethostname() . ':' . getmypid() . ':' . Str::random(8);
	}

	/**
	 * Output message to CLI
	 */
	protected function output(string $message, string $type = 'info'): void
	{
		if ($this->cli === null) {
			return;
		}

		$timestamp = date('Y-m-d H:i:s');
		$message = "[{$timestamp}] {$message}";

		match ($type) {
			'success' => $this->cli->success($message),
			'error' => $this->cli->error($message),
			'warning' => $this->cli->warning($message),
			default => $this->cli->info($message)
		};
	}

	/**
	 * Get worker status
	 */
	public function status(): array
	{
		return [
			'workerId' => $this->workerId,
			'running' => !$this->shouldStop,
			'startTime' => $this->startTime,
			'uptime' => microtime(true) - $this->startTime,
			'jobsProcessed' => $this->jobsProcessed,
			'memoryUsage' => memory_get_usage(true),
			'peakMemoryUsage' => memory_get_peak_usage(true)
		];
	}
}