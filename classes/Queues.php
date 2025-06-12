<?php

namespace tobimori\Queues;

use Kirby\Cms\App;
use Kirby\Exception\InvalidArgumentException;

/**
 * Main entry point for the Queues system
 *
 * This class provides a simple facade for interacting with the queue system,
 * including pushing jobs, scheduling recurring tasks, and managing the queue.
 */
class Queues
{
	/**
	 * @var Manager|null The queue manager instance
	 * @internal
	 */
	protected static ?Manager $manager = null;

	/**
	 * @var array<string, class-string<Job>> Registered job classes
	 * @internal
	 */
	protected static array $jobs = [];

	/**
	 * @var array<string> Registered queue names
	 * @internal
	 */
	protected static array $queues = [];

	/**
	 * Initialize the queue system
	 *
	 * Called automatically by the plugin hook on system.loadPlugins:after
	 *
	 * @internal
	 */
	public static function init(): void
	{
		static::$manager = new Manager();

		// Register default queues from config
		$defaultQueues = App::instance()->option('tobimori.queues.queues', ['default', 'high', 'low']);
		foreach ($defaultQueues as $queue) {
			static::registerQueue($queue);
		}
	}

	/**
	 * Register job classes from other plugins
	 *
	 * @param string|array<string> $jobs Job class names to register
	 * @throws InvalidArgumentException If job class doesn't extend Job base class
	 */
	public static function register(string|array $jobs): void
	{
		$jobs = is_string($jobs) ? [$jobs] : $jobs;

		foreach ($jobs as $job) {
			// validate job class exists and extends base Job class
			if (!class_exists($job)) {
				throw new InvalidArgumentException("Job class '{$job}' does not exist");
			}

			if (!is_subclass_of($job, Job::class)) {
				throw new InvalidArgumentException("Job class '{$job}' must extend " . Job::class);
			}

			// get job type from class
			$instance = new $job();
			static::$jobs[$instance->type()] = $job;
		}
	}

	/**
	 * Push a job to the queue
	 *
	 * @param array<string, mixed> $payload Job payload data
	 * @throws InvalidArgumentException If job type is not registered
	 */
	public static function push(string|Job $job, array $payload = [], ?string $queue = null): string
	{
		return static::manager()->push($job, $payload, $queue);
	}

	/**
	 * Push a job with delay
	 *
	 * @param array<string, mixed> $payload Job payload data
	 * @throws InvalidArgumentException If job type is not registered
	 */
	public static function later(int $delay, string|Job $job, array $payload = [], ?string $queue = null): string
	{
		return static::manager()->later($delay, $job, $payload, $queue);
	}

	/**
	 * Schedule a recurring job
	 *
	 * @param array<string, mixed> $payload Job payload data
	 * @throws InvalidArgumentException If job type is not registered or cron expression is invalid
	 */
	public static function schedule(string $expression, string|Job $job, array $payload = []): void
	{
		static::scheduler()->schedule($expression, $job, $payload);
	}

	/**
	 * Get queue manager instance
	 *
	 * @throws \RuntimeException If queue system not initialized
	 */
	public static function manager(): Manager
	{
		if (static::$manager === null) {
			throw new \RuntimeException('Queue system not initialized. Did you forget to call Queues::init()?');
		}

		return static::$manager;
	}

	/**
	 * Get scheduler instance
	 *
	 * @throws \RuntimeException If queue system not initialized
	 */
	public static function scheduler(): Schedule\Scheduler
	{
		return static::manager()->scheduler();
	}

	/**
	 * Get registered job classes
	 *
	 * @return array<string, class-string<Job>>
	 */
	public static function jobs(): array
	{
		return static::$jobs;
	}

	/**
	 * Get job class by type
	 */
	public static function job(string $type): ?string
	{
		return static::$jobs[$type] ?? null;
	}

	/**
	 * Create job instance from type
	 *
	 * @param array<string, mixed> $payload Job payload data
	 * @throws InvalidArgumentException If job type is not registered
	 * @internal
	 */
	public static function createJob(string $type, array $payload = []): Job
	{
		$class = static::job($type);

		if ($class === null) {
			throw new InvalidArgumentException("Job type '{$type}' is not registered");
		}

		/** @var Job $job */
		$job = new $class();
		$job->setPayload($payload);

		return $job;
	}

	/**
	 * Register a queue name
	 *
	 * @param string|array<string> $queues Queue names to register
	 */
	public static function registerQueue(string|array $queues): void
	{
		$queues = is_string($queues) ? [$queues] : $queues;

		foreach ($queues as $queue) {
			if (!in_array($queue, static::$queues)) {
				static::$queues[] = $queue;
			}
		}
	}

	/**
	 * Get all registered queue names
	 *
	 * @return array<string>
	 */
	public static function registeredQueues(): array
	{
		// Get config queues
		$configQueues = App::instance()->option('tobimori.queues.queues') ?? ['default', 'high', 'low'];

		// Merge config queues with dynamically registered queues, ensuring 'default' is always first
		$allQueues = array_unique(array_merge(['default'], $configQueues, static::$queues));

		return array_values($allQueues);
	}
}
