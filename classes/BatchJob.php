<?php

namespace tobimori\Queues;

/**
 * Abstract base class for batched queue jobs
 *
 * When multiple dispatches of the same batch job type happen within
 * a time window, their payloads accumulate and fire as a single job
 * with an array of all collected payloads.
 *
 * `handle()` receives `$this->payload()` as `array<int, array<string, mixed>>`
 */
abstract class BatchJob extends Job
{
	/**
	 * Get the batch window in seconds
	 */
	public function batchWindow(): int
	{
		return 30;
	}

	/**
	 * Get the batch key used to group payloads
	 *
	 * Override this to create separate batches for the same job type
	 * (e.g. per-tenant batching).
	 */
	public function batchKey(): string
	{
		return $this->type();
	}
}
