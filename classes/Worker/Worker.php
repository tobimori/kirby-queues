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
	 * @var array<string, mixed> Worker options
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
	 *
	 * @param array<string, mixed> $options Worker configuration options
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
	 *
	 * @param string|array<string>|null $queues Queue names to process
	 */
	public function work(string|array|null $queues = null, bool $once = false): void
	{
		$queues = $this->normalizeQueues($queues);

		$this->cli->br();
		$this->cli->bold()->out('🚀 Queue Worker Started');
		$this->cli->br();

		$padding = $this->cli->padding(25)->char('.');
		$padding->label('Worker ID')->result(substr($this->workerId, 0, 20) . '...');
		$padding->label('Processing queues')->result(implode(', ', $queues));
		$padding->label('Mode')->result($once ? 'Single job' : 'Continuous');
		$this->cli->br();

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

		if ($this->cli) {
			$this->cli->br();
			$this->cli->bold()->out('✋ Worker stopped');
			$this->cli->br();
		}
	}

	/**
	 * Process a single job
	 */
	public function processJob(Job $job): JobResult
	{
		$startTime = microtime(true);

		$this->output("Job started", 'info');

		// mark job as running (this increments attempts in storage)
		$this->manager->markRunning($job->id(), $this->workerId);

		// Add log entry for job start
		$this->manager->addJobLog($job->id(), 'info', 'Job started', [
			'worker' => $this->workerId,
			'attempt' => $job->attempts()
		]);

		try {
			// set up timeout if configured
			$this->setupTimeout($job);

			// Pass CLI instance to job for output
			$job->setCli($this->cli);

			// refresh kirby's internal state to see newly created pages/files
			$this->refreshKirbyState();

			// execute the job
			$job->handle();

			// Add log entry for job completion
			$this->manager->addJobLog($job->id(), 'info', 'Job completed successfully');

			// clear timeout
			$this->clearTimeout($job);

			// mark as completed
			$this->manager->markCompleted($job->id());

			$result = new JobResult(
				$job->id(),
				JobStatus::COMPLETED,
				$startTime,
				microtime(true)
			);

			$this->output("Job completed successfully", 'info');

			return $result;
		} catch (\Exception $e) {
			// clear timeout
			$this->clearTimeout($job);

			return $this->handleFailedJob($job, $e, $startTime);
		}
	}

	/**
	 * Refresh kirby's internal state
	 *
	 * clears PHP's stat cache and resets kirby's cached objects
	 * to ensure newly created pages/files are visible in long-running workers
	 */
	protected function refreshKirbyState(): void
	{
		// clear php's filesystem stat cache
		clearstatcache(true);

		// reset kirby's version cache (content fields)
		\Kirby\Content\VersionCache::reset();

		// force site object to be recreated on next access
		// this ensures fresh inventory of pages/files
		App::instance()->setSite(null);
	}

	/**
	 * Handle a failed job
	 */
	protected function handleFailedJob(Job $job, \Exception $exception, float $startTime): JobResult
	{
		$this->output("Job failed: " . $exception->getMessage(), 'error');

		// Add log entry for job failure
		$this->manager->addJobLog($job->id(), 'error', 'Job failed: ' . $exception->getMessage(), [
			'exception' => get_class($exception),
			'file' => $exception->getFile(),
			'line' => $exception->getLine()
		]);

		// check if job should be retried
		if ($job->shouldRetry()) {
			$delay = $job->retryBackoff();

			$this->manager->release($job->id(), $delay);

			$this->output("Job will be retried in {$delay} seconds (attempt {$job->attempts()}/{$job->maxAttempts()})", 'warning');

			// Add log entry for retry
			$this->manager->addJobLog($job->id(), 'warning', "Job will be retried in {$delay} seconds", [
				'attempt' => $job->attempts(),
				'max_attempts' => $job->maxAttempts(),
				'delay' => $delay
			]);

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

		// Add log entry for permanent failure
		$this->manager->addJobLog($job->id(), 'error', 'Job permanently failed after all retries', [
			'attempts' => $job->attempts(),
			'max_attempts' => $job->maxAttempts()
		]);

		// call job's failed method
		try {
			$job->failed($exception);
		} catch (\Exception $e) {
			// Log failed handler exception
			$this->manager->addJobLog($job->id(), 'error', 'Failed handler threw exception: ' . $e->getMessage());
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

			if (!empty($queued) && $this->cli) {
				$this->cli->dim()->out("📅 Queued " . count($queued) . " scheduled job(s)");
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
	 *
	 * @param string|array<string>|null $queues Queue names to normalize
	 * @return array<string> Normalized queue names
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
			if ($this->cli) {
				$this->cli->dim()->out("🔄 Max jobs reached ({$this->jobsProcessed}/{$maxJobs}) - restarting");
			}
			return true;
		}

		// restart if memory limit exceeded
		$memoryLimit = ($this->options['memory'] ?? 128) * 1024 * 1024;
		if (memory_get_usage(true) >= $memoryLimit) {
			if ($this->cli) {
				$this->cli->yellow()->out("⚠️ Memory limit exceeded ({$this->options['memory']}MB) - restarting");
			}
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
	 * Setup timeout for job execution
	 */
	protected function setupTimeout(Job $job): void
	{
		$timeout = $job->timeout();
		if ($timeout > 0 && function_exists('pcntl_alarm')) {
			pcntl_signal(SIGALRM, function () use ($job) {
				throw new \RuntimeException("Job {$job->id()} timed out after {$job->timeout()} seconds");
			});
			pcntl_alarm($timeout);
		}
	}

	/**
	 * Clear timeout after job execution
	 */
	protected function clearTimeout(Job $job): void
	{
		$timeout = $job->timeout();
		if ($timeout > 0 && function_exists('pcntl_alarm')) {
			pcntl_alarm(0);
		}
	}

	/**
	 * Output message to CLI
	 */
	protected function output(string $message, string $type = 'info'): void
	{
		if ($this->cli === null) {
			return;
		}

		$timestamp = date('d.m.Y H:i:s:');
		$typeConfigs = [
			'success' => ['color' => 'green', 'label' => 'SUCCESS'],
			'error' => ['color' => 'red', 'label' => 'ERROR   '],
			'warning' => ['color' => 'yellow', 'label' => 'WARNING'],
			'debug' => ['color' => 'dim', 'label' => 'DEBUG   '],
			'info' => ['color' => 'blue', 'label' => 'INFO    ']
		];

		$config = $typeConfigs[$type] ?? $typeConfigs['info'];
		$color = $config['color'];
		$label = $config['label'];

		if ($type === 'debug') {
			$this->cli->out("<dim>{$timestamp}  {$label}  {$message}</dim>");
		} else {
			$this->cli->out("<{$color}>{$timestamp}</{$color}>  <bold><{$color}>{$label}</{$color}></bold>  {$message}");
		}
	}

	/**
	 * Get worker status
	 *
	 * @return array<string, mixed>
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
