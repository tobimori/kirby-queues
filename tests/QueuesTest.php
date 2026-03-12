<?php

use tobimori\Queues\Queues;
use tobimori\Queues\JobStatus;
use tobimori\Queues\Worker\Worker;
use tobimori\Queues\Tests\Jobs\TestJob;
use tobimori\Queues\Tests\Jobs\FailingTestJob;
use tobimori\Queues\Tests\Jobs\ProcessDataJob;

test('can retrieve job from queue', function () {
	$jobId = queue(ProcessDataJob::class, ['data' => 'test']);

	$nextJob = Queues::manager()->pop('default');

	expect($nextJob)->not->toBeNull();
	expect($nextJob)->toBeInstanceOf(ProcessDataJob::class);
	expect($nextJob->payload())->toBe(['data' => 'test']);
	expect($nextJob->id())->toBe($jobId);
});

test('queue statistics work', function () {
	queue(TestJob::class);
	queue(ProcessDataJob::class, ['data' => 'stats']);

	$stats = Queues::manager()->stats();

	expect($stats)->toHaveKey('total');
	expect($stats)->toHaveKey('by_status');
	expect($stats['total'])->toBe(2);
});

test('push stores job with correct data', function () {
	$jobId = queue(ProcessDataJob::class, ['key' => 'value'], 'high');

	$jobData = Queues::manager()->storage()->get($jobId);

	expect($jobData)->not->toBeNull();
	expect($jobData['id'])->toBe($jobId);
	expect($jobData['type'])->toBe('process-data');
	expect($jobData['payload'])->toBe(['key' => 'value']);
	expect($jobData['queue'])->toBe('high');
	expect($jobData['status'])->toBe(JobStatus::PENDING->value);
	expect($jobData['available_at'])->toBeLessThanOrEqual(time());
});

test('push with delay sets future available_at', function () {
	$before = time();
	$jobId = Queues::later(120, ProcessDataJob::class, ['delayed' => true]);
	$jobData = Queues::manager()->storage()->get($jobId);

	expect($jobData['available_at'])->toBeGreaterThanOrEqual($before + 120);
});

test('worker processes job to completion', function () {
	ProcessDataJob::reset();

	$jobId = queue(ProcessDataJob::class, ['data' => 'worker-test']);

	$job = Queues::manager()->pop('default');
	expect($job)->not->toBeNull();

	$worker = new Worker(Queues::manager(), null);
	$result = $worker->processJob($job);

	expect($result->status())->toBe(JobStatus::COMPLETED);
	expect($result->success())->toBeTrue();
	expect(ProcessDataJob::$processed)->toContain('worker-test');

	$storedJob = Queues::manager()->storage()->get($jobId);
	expect($storedJob['status'])->toBe(JobStatus::COMPLETED->value);
});

test('worker retries failed job with backoff', function () {
	$jobId = queue(FailingTestJob::class);

	$job = Queues::manager()->pop('default');
	expect($job)->not->toBeNull();

	$before = time();
	$worker = new Worker(Queues::manager(), null);
	$result = $worker->processJob($job);

	// default maxAttempts is 3, first attempt fails so it should be retried
	expect($result->status())->toBe(JobStatus::PENDING);
	expect($result->exception())->toBeInstanceOf(\Exception::class);

	$storedJob = Queues::manager()->storage()->get($jobId);
	expect($storedJob['status'])->toBe(JobStatus::PENDING->value);
	expect($storedJob['available_at'])->toBeGreaterThanOrEqual($before);
	expect($storedJob['attempts'])->toBe(1);
});
