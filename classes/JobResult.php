<?php

namespace tobimori\Queues;

/**
 * Job result wrapper
 *
 * Encapsulates the result of a job execution, including timing information,
 * status, and any output or error data.
 */
class JobResult
{
	/**
	 * Constructor
	 */
	public function __construct(
		protected string $jobId,
		protected JobStatus $status,
		protected float $startTime,
		protected float $endTime,
		protected mixed $output = null,
		protected ?\Exception $exception = null
	) {
	}

	/**
	 * Get job ID
	 */
	public function jobId(): string
	{
		return $this->jobId;
	}

	/**
	 * Get job status
	 */
	public function status(): JobStatus
	{
		return $this->status;
	}

	/**
	 * Check if job succeeded
	 */
	public function success(): bool
	{
		return $this->status === JobStatus::COMPLETED;
	}

	/**
	 * Check if job failed
	 */
	public function failed(): bool
	{
		return $this->status === JobStatus::FAILED;
	}

	/**
	 * Get execution duration in seconds
	 */
	public function duration(): float
	{
		return $this->endTime - $this->startTime;
	}

	/**
	 * Get execution duration in milliseconds
	 */
	public function durationMs(): int
	{
		return (int) round($this->duration() * 1000);
	}

	/**
	 * Get job output
	 */
	public function output(): mixed
	{
		return $this->output;
	}

	/**
	 * Get exception if job failed
	 */
	public function exception(): ?\Exception
	{
		return $this->exception;
	}

	/**
	 * Get error message if job failed
	 */
	public function error(): ?string
	{
		return $this->exception?->getMessage();
	}

	/**
	 * Convert to array for storage/serialization
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'jobId' => $this->jobId,
			'status' => $this->status->value,
			'success' => $this->success(),
			'duration' => $this->duration(),
			'durationMs' => $this->durationMs(),
			'output' => $this->output,
			'error' => $this->error(),
			'exception' => $this->exception ? [
				'class' => get_class($this->exception),
				'message' => $this->exception->getMessage(),
				'code' => $this->exception->getCode(),
				'file' => $this->exception->getFile(),
				'line' => $this->exception->getLine()
			] : null
		];
	}
}
