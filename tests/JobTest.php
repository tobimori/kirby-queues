<?php

use tobimori\Queues\Tests\Jobs\TestJob;
use tobimori\Queues\Tests\Jobs\ProcessDataJob;

test('job stores payload correctly', function () {
	$job = new ProcessDataJob();
	$job->setPayload(['key' => 'value']);

	expect($job->payload())->toBe(['key' => 'value']);
});

test('job can set queue name', function () {
	$job = new TestJob();
	$job->onQueue('high');

	expect($job->options()['queue'])->toBe('high');
});

test('job can set delay', function () {
	$job = new TestJob();
	$job->delay(300);

	expect($job->options()['delay'])->toBe(300);
});

test('job can set max attempts', function () {
	$job = new TestJob();
	$job->tries(5);

	expect($job->options()['attempts'])->toBe(5);
	expect($job->maxAttempts())->toBe(5);
});

test('job tracks attempt count', function () {
	$job = new TestJob();
	$job->setAttempts(2);

	expect($job->attempts())->toBe(2);
});

test('job determines retry eligibility', function () {
	$job = new TestJob();
	$job->tries(3);

	$job->setAttempts(2);
	expect($job->shouldRetry())->toBeTrue();

	$job->setAttempts(3);
	expect($job->shouldRetry())->toBeFalse();
});

test('process data job tracks processed items', function () {
	$job1 = new ProcessDataJob();
	$job1->setPayload(['data' => 'first']);
	$job1->handle();

	$job2 = new ProcessDataJob();
	$job2->setPayload(['data' => 'second']);
	$job2->handle();

	expect(ProcessDataJob::$processed)->toBe(['first', 'second']);
});
