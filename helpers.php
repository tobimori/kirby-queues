<?php

use tobimori\Queues\Queues;

/**
 * Push a job to the queue
 *
 * @param array<string, mixed> $payload Job payload data
 */
function queue(string|tobimori\Queues\Job $job, array $payload = [], ?string $queue = null): string
{
	return Queues::push($job, $payload, $queue);
}

/**
 * Push a job to the queue with delay
 *
 * @param array<string, mixed> $payload Job payload data
 */
function queueLater(int $delay, string|tobimori\Queues\Job $job, array $payload = [], ?string $queue = null): string
{
	return Queues::later($delay, $job, $payload, $queue);
}
