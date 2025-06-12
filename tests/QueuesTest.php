<?php

use tobimori\Queues\Queues;
use tobimori\Queues\Tests\Jobs\TestJob;
use tobimori\Queues\Tests\Jobs\FailingTestJob;
use tobimori\Queues\Tests\Jobs\ProcessDataJob;

test('can push job to queue', function () {
	$job = new TestJob();
	$job->setPayload(['test' => 'data']);
	$jobId = Queues::push($job);

	expect($jobId)->toBeString();
});

test('can push job with delay', function () {
	$job = new TestJob();
	$jobId = Queues::later(60, $job);

	expect($jobId)->toBeString();
});

test('can retrieve job from queue', function () {
	// Push job using the helper which handles payload correctly
	queue(ProcessDataJob::class, ['data' => 'test']);

	$nextJob = Queues::manager()->pop('default');

	expect($nextJob)->not->toBeNull();
	expect($nextJob)->toBeInstanceOf(ProcessDataJob::class);
	expect($nextJob->payload())->toBe(['data' => 'test']);
});

test('job execution works', function () {
	$job = new TestJob();
	$job->handle(); // Simply execute without throwing
	expect(true)->toBeTrue();
});

test('failing job throws exception', function () {
	$job = new FailingTestJob();
	expect(fn() => $job->handle())->toThrow(Exception::class);
});

test('can use helper function', function () {
	$jobId = queue(ProcessDataJob::class, ['data' => 'test-value']);

	expect($jobId)->toBeString();

	$job = Queues::manager()->pop('default');
	expect($job)->toBeInstanceOf(ProcessDataJob::class);
	expect($job->payload())->toBe(['data' => 'test-value']);
});

test('queue statistics work', function () {
	queue(TestJob::class);
	queue(ProcessDataJob::class, ['data' => 'stats']);

	$stats = Queues::manager()->stats();

	expect($stats)->toHaveKey('total');
	expect($stats)->toHaveKey('by_status');
	expect($stats['total'])->toBeGreaterThanOrEqual(2);
});
