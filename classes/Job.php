<?php

namespace tobimori\Queues;

use Kirby\Cms\App;
use Kirby\CLI\CLI;

/**
 * Abstract base class for all queue jobs
 *
 * This class provides the foundation for creating custom jobs that can be
 * processed by the queue system. Jobs encapsulate units of work that can
 * be executed asynchronously.
 */
abstract class Job
{
	/**
	 * @var array Job payload data
	 */
	protected array $payload = [];

	/**
	 * @var array Job options (queue, delay, attempts, etc.)
	 */
	protected array $options = [];

	/**
	 * @var int Current attempt number
	 */
	protected int $attempts = 0;

	/**
	 * @var string|null Job ID (set when job is pushed to queue)
	 * @internal
	 */
	protected ?string $id = null;


	/**
	 * @var CLI|null CLI instance for output
	 * @internal
	 */
	protected ?CLI $cli = null;

	/**
	 * Unique job type identifier
	 *
	 * This should be a unique string that identifies this job type across
	 * all plugins. Recommended format: 'vendor:job-name'
	 */
	abstract public function type(): string;

	/**
	 * Human-readable job name
	 *
	 * This is displayed in the panel interface and logs
	 */
	abstract public function name(): string;

	/**
	 * Execute the job
	 *
	 * This method contains the actual work that the job performs.
	 * It can throw exceptions which will be caught and handled by the worker.
	 *
	 * @throws \Exception If job execution fails
	 */
	abstract public function handle(): void;

	/**
	 * Handle job failure
	 *
	 * Called when the job fails after all retry attempts are exhausted.
	 * Override this method to implement custom failure handling logic.
	 */
	public function failed(\Exception $exception): void
	{
		// override in subclass if needed
	}

	/**
	 * Set job payload
	 *
	 * @internal
	 */
	public function setPayload(array $payload): static
	{
		$this->payload = $payload;
		return $this;
	}

	/**
	 * Get job payload
	 */
	public function payload(): array
	{
		return $this->payload;
	}

	/**
	 * Set job options
	 *
	 * @internal
	 */
	public function setOptions(array $options): static
	{
		$this->options = $options;
		return $this;
	}

	/**
	 * Get job options
	 */
	public function options(): array
	{
		return $this->options;
	}

	/**
	 * Set queue name for this job
	 */
	public function onQueue(string $queue): static
	{
		$this->options['queue'] = $queue;
		return $this;
	}

	/**
	 * Set delay for this job
	 */
	public function delay(int $seconds): static
	{
		$this->options['delay'] = $seconds;
		return $this;
	}

	/**
	 * Set maximum attempts for this job
	 */
	public function tries(int $attempts): static
	{
		$this->options['attempts'] = $attempts;
		return $this;
	}

	/**
	 * Set current attempt number
	 *
	 * @internal
	 */
	public function setAttempts(int $attempts): static
	{
		$this->attempts = $attempts;
		return $this;
	}

	/**
	 * Get current attempt number
	 */
	public function attempts(): int
	{
		return $this->attempts;
	}

	/**
	 * Determine if job should be retried
	 */
	public function shouldRetry(): bool
	{
		return $this->attempts < $this->maxAttempts();
	}

	/**
	 * Get maximum attempts
	 */
	public function maxAttempts(): int
	{
		return $this->options['attempts'] ?? App::instance()->option('tobimori.queues.worker.tries', 3);
	}

	/**
	 * Set CLI instance for output
	 * @internal
	 */
	public function setCli(?CLI $cli): static
	{
		$this->cli = $cli;
		return $this;
	}

	/**
	 * Get retry backoff time in seconds
	 */
	public function retryBackoff(): int
	{
		$backoff = $this->options['backoff'] ?? App::instance()->option('tobimori.queues.worker.backoff', 60);

		// exponential backoff: backoff * (attempt ^ 2)
		return $backoff * ($this->attempts ** 2);
	}

	/**
	 * Set job ID
	 *
	 * @internal
	 */
	public function setId(string $id): static
	{
		$this->id = $id;
		return $this;
	}

	/**
	 * Get job ID
	 */
	public function id(): ?string
	{
		return $this->id;
	}


	/**
	 * Add a log entry
	 */
	protected function log(string $level, string $message, array $context = []): void
	{
		if ($this->id !== null) {
			Queues::manager()->addJobLog($this->id, $level, $message, $context);
		}

		// Also output to CLI if available
		if ($this->cli !== null) {
			$timestamp = date('d.m.Y H:i:s:');

			match ($level) {
				'error' => $this->cli->out("<red>{$timestamp}</red>  <bold><red>ERROR</red></bold>    {$message}"),
				'warning' => $this->cli->out("<yellow>{$timestamp}</yellow>  <bold><yellow>WARNING</yellow></bold>  {$message}"),
				'debug' => $this->cli->out("<dim>{$timestamp}  DEBUG    {$message}</dim>"),
				default => $this->cli->out("<blue>{$timestamp}</blue>  <bold><blue>INFO</blue></bold>     {$message}")
			};
		}
	}



	/**
	 * Get timeout for this job in seconds
	 */
	public function timeout(): int
	{
		return $this->options['timeout'] ?? App::instance()->option('tobimori.queues.worker.timeout', 60);
	}

	/**
	 * Serialize job for storage
	 *
	 * @internal
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->id,
			'type' => $this->type(),
			'name' => $this->name(),
			'payload' => $this->payload,
			'options' => $this->options,
			'attempts' => $this->attempts
		];
	}

	/**
	 * Restore job from storage
	 *
	 * @internal
	 */
	public static function fromArray(array $data): static
	{
		$job = Queues::createJob($data['type'], $data['payload'] ?? []);

		$job->setId($data['id'] ?? null)
			->setOptions($data['options'] ?? [])
			->setAttempts($data['attempts'] ?? 0);

		return $job;
	}
}
