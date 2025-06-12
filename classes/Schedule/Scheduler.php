<?php

namespace tobimori\Queues\Schedule;

use Kirby\Cms\App;
use Kirby\Exception\InvalidArgumentException;
use tobimori\Queues\Job;
use tobimori\Queues\Manager;
use tobimori\Queues\Queues;

/**
 * Job scheduler
 *
 * Manages scheduled/recurring jobs using cron expressions.
 * The scheduler is responsible for checking which scheduled jobs
 * are due and pushing them to the queue.
 */
class Scheduler
{
	/**
	 * @var Manager Queue manager instance
	 */
	protected Manager $manager;

	/**
	 * @var array<string, array<string, mixed>> Scheduled job configurations
	 */
	protected array $scheduled = [];

	/**
	 * Constructor
	 */
	public function __construct(Manager $manager)
	{
		$this->manager = $manager;
		$this->loadScheduled();
	}

	/**
	 * Schedule a recurring job
	 *
	 * @param array<string, mixed> $payload Job payload data
	 * @param array<string, mixed> $options Schedule options
	 * @throws InvalidArgumentException
	 */
	public function schedule(string $expression, string|Job $job, array $payload = [], array $options = []): void
	{
		// validate cron expression
		if (!CronExpression::isValid($expression)) {
			throw new InvalidArgumentException("Invalid cron expression: {$expression}");
		}

		// get job type
		if (is_string($job)) {
			// check if it's a class name or job type
			if (class_exists($job) && is_subclass_of($job, Job::class)) {
				// it's a class name, create instance to get type
				$instance = new $job();
				$jobType = $instance->type();

				// ensure the job type is registered
				if (Queues::job($jobType) === null) {
					Queues::register($job);
				}
			} else {
				// it's a job type, validate it exists
				$jobInstance = Queues::createJob($job, $payload);
				$jobType = $jobInstance->type();
			}
		} else {
			$jobType = $job->type();
		}

		// create scheduled job configuration
		$scheduled = [
			'id' => $this->generateScheduleId($jobType, $expression),
			'expression' => $expression,
			'job' => $jobType,
			'payload' => $payload,
			'options' => $options,
			'timezone' => $options['timezone'] ?? App::instance()->option('tobimori.queues.schedule.timezone', 'UTC'),
			'lastRun' => null,
			'nextRun' => null
		];

		// calculate next run time
		$scheduled['nextRun'] = $this->calculateNextRun($scheduled);

		// add to scheduled jobs
		$this->scheduled[$scheduled['id']] = $scheduled;
		$this->saveScheduled();
	}

	/**
	 * Remove a scheduled job
	 */
	public function unschedule(string $id): void
	{
		unset($this->scheduled[$id]);
		$this->saveScheduled();
	}

	/**
	 * Get all scheduled jobs
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array
	{
		return $this->scheduled;
	}

	/**
	 * Get scheduled job by ID
	 *
	 * @return array<string, mixed>|null
	 */
	public function find(string $id): ?array
	{
		return $this->scheduled[$id] ?? null;
	}

	/**
	 * Check and run due scheduled jobs
	 *
	 * This method should be called periodically (e.g., every minute)
	 * by the worker to check for scheduled jobs that are due.
	 *
	 * @return array<string> Array of queued job IDs
	 */
	public function runDue(): array
	{
		$queued = [];
		$now = time();

		foreach ($this->scheduled as &$schedule) {
			// skip if not due yet
			if ($schedule['nextRun'] === null || $schedule['nextRun'] > $now) {
				continue;
			}

			// check for overlapping runs if configured
			if ($this->shouldPreventOverlap($schedule) && $this->isRunning($schedule)) {
				continue;
			}

			// push job to queue
			try {
				$queue = $schedule['options']['queue'] ?? null;
				$jobId = $this->manager->push($schedule['job'], $schedule['payload'], $queue);
				$queued[] = $jobId;

				// update schedule
				$schedule['lastRun'] = $now;
				$schedule['nextRun'] = $this->calculateNextRun($schedule);

				// track running job if overlap prevention is enabled
				if ($this->shouldPreventOverlap($schedule)) {
					$this->trackRunning($schedule, $jobId);
				}
			} catch (\Exception $e) {
				// log error but continue with other scheduled jobs
				error_log("Failed to queue scheduled job {$schedule['id']}: " . $e->getMessage());
			}
		}

		// save updated schedule data
		if (!empty($queued)) {
			$this->saveScheduled();
		}

		return $queued;
	}

	/**
	 * Calculate next run time for a schedule
	 *
	 * @param array<string, mixed> $schedule Schedule configuration
	 */
	protected function calculateNextRun(array $schedule): ?int
	{
		$cron = new CronExpression($schedule['expression']);
		$timezone = new \DateTimeZone($schedule['timezone']);
		$now = new \DateTime('now', $timezone);

		// if we have a last run, start from there
		if ($schedule['lastRun'] !== null) {
			$from = new \DateTime('@' . $schedule['lastRun']);
			$from->setTimezone($timezone);
			// add one minute to avoid getting the same time
			$from->modify('+1 minute');
		} else {
			$from = $now;
		}

		$next = $cron->getNextRunDate($from);

		return $next ? $next->getTimestamp() : null;
	}

	/**
	 * Check if overlap prevention is enabled for a schedule
	 *
	 * @param array<string, mixed> $schedule Schedule configuration
	 */
	protected function shouldPreventOverlap(array $schedule): bool
	{
		// If withoutOverlapping is explicitly set, use that
		if (isset($schedule['options']['withoutOverlapping'])) {
			return $schedule['options']['withoutOverlapping'];
		}

		// Otherwise check global config (default is to prevent overlap)
		return App::instance()->option('tobimori.queues.schedule.preventOverlap', true);
	}

	/**
	 * Check if a scheduled job is currently running
	 *
	 * @param array<string, mixed> $schedule Schedule configuration
	 */
	protected function isRunning(array $schedule): bool
	{
		$runningKey = 'queues.schedule.running.' . $schedule['id'];
		$jobId = App::instance()->cache('queues')->get($runningKey);

		if ($jobId === null) {
			return false;
		}

		// check if the job is still running
		$jobData = $this->manager->storage()->get($jobId);

		if ($jobData === null || !in_array($jobData['status'], ['pending', 'running'])) {
			// job is no longer running, clear the flag
			App::instance()->cache('queues')->remove($runningKey);
			return false;
		}

		return true;
	}

	/**
	 * Track a running scheduled job
	 *
	 * @param array<string, mixed> $schedule Schedule configuration
	 */
	protected function trackRunning(array $schedule, string $jobId): void
	{
		$runningKey = 'queues.schedule.running.' . $schedule['id'];
		// store for 24 hours (should be cleared when job completes)
		App::instance()->cache('queues')->set($runningKey, $jobId, 86400);
	}

	/**
	 * Generate schedule ID
	 */
	protected function generateScheduleId(string $jobType, string $expression): string
	{
		return md5($jobType . '|' . $expression);
	}

	/**
	 * Load scheduled jobs from storage
	 */
	protected function loadScheduled(): void
	{
		$this->scheduled = $this->manager->storage()->getScheduled();
	}

	/**
	 * Save scheduled jobs to storage
	 */
	protected function saveScheduled(): void
	{
		$this->manager->storage()->saveScheduled($this->scheduled);
	}
}
