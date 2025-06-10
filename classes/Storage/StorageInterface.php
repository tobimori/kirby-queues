<?php

namespace tobimori\Queues\Storage;

/**
 * Storage interface for queue jobs
 *
 * Defines the contract for queue storage implementations.
 * Storage backends must implement atomic operations to ensure
 * queue integrity in concurrent environments.
 */
interface StorageInterface
{
	/**
	 * Push job to queue
	 */
	public function push(string $queue, array $job): void;

	/**
	 * Pop next job from queue
	 *
	 * This operation must be atomic to prevent multiple workers
	 * from processing the same job.
	 */
	public function pop(string $queue): ?array;

	/**
	 * Get job by ID
	 */
	public function get(string $id): ?array;

	/**
	 * Update job data
	 */
	public function update(string $id, array $data): void;

	/**
	 * Delete job
	 */
	public function delete(string $id): void;

	/**
	 * Get jobs by status
	 */
	public function getByStatus(string $status, int $limit = 100, int $offset = 0): array;

	/**
	 * Get queue statistics
	 */
	public function stats(): array;

	/**
	 * Get all queues with job counts
	 */
	public function queues(): array;

	/**
	 * Clear old completed/failed jobs
	 */
	public function clear(int $completedHours = 24, int $failedHours = 168): int;

	/**
	 * Get scheduled jobs
	 */
	public function getScheduled(): array;

	/**
	 * Save scheduled jobs
	 */
	public function saveScheduled(array $scheduled): void;

	/**
	 * Mark job as running
	 */
	public function markRunning(string $id, string $workerId): void;

	/**
	 * Mark job as completed
	 */
	public function markCompleted(string $id, mixed $result = null): void;

	/**
	 * Mark job as failed
	 */
	public function markFailed(string $id, string $error, array $exception = []): void;

	/**
	 * Release job back to queue
	 *
	 * Used when a job needs to be retried after failure
	 */
	public function release(string $id, int $delay = 0): void;
}