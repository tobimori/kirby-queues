<?php

namespace tobimori\Queues;

/**
 * Job status enumeration
 *
 * Represents the various states a job can be in during its lifecycle
 */
enum JobStatus: string
{
	/**
	 * Job is waiting to be processed
	 */
	case PENDING = 'pending';

	/**
	 * Job is currently being processed
	 */
	case RUNNING = 'running';

	/**
	 * Job completed successfully
	 */
	case COMPLETED = 'completed';

	/**
	 * Job failed after all retry attempts
	 */
	case FAILED = 'failed';

	/**
	 * Get human-readable label for status
	 */
	public function label(): string
	{
		$label = t('queues.status.' . $this->value);
		return is_string($label) ? $label : $this->value;
	}

	/**
	 * Get CSS class for status styling
	 */
	public function cssClass(): string
	{
		return match ($this) {
			self::PENDING => 'k-status-icon--pending',
			self::RUNNING => 'k-status-icon--active',
			self::COMPLETED => 'k-status-icon--completed',
			self::FAILED => 'k-status-icon--error'
		};
	}

	/**
	 * Check if job is in a terminal state
	 */
	public function isTerminal(): bool
	{
		return match ($this) {
			self::COMPLETED, self::FAILED => true,
			default => false
		};
	}

	/**
	 * Check if job can be retried
	 */
	public function canRetry(): bool
	{
		return $this === self::FAILED;
	}
}
