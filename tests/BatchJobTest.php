<?php

use tobimori\Queues\Queues;
use tobimori\Queues\Tests\Jobs\BatchTestJob;
use tobimori\Queues\Worker\Worker;

beforeEach(function () {
	BatchTestJob::reset();

	$storage = Queues::manager()->storage();
	$storage->flushBatchPayloads('batch-test-job');
});

test('batch key defaults to type', function () {
	$job = new BatchTestJob();
	expect($job->batchKey())->toBe('batch-test-job');
});

test('dispatching batch job accumulates payloads', function () {
	$storage = Queues::manager()->storage();

	$id1 = queue(BatchTestJob::class, ['user_id' => 1]);
	$id2 = queue(BatchTestJob::class, ['user_id' => 2]);
	$id3 = queue(BatchTestJob::class, ['user_id' => 3]);

	expect($id1)->toBe($id2)->toBe($id3);

	$payloads = $storage->flushBatchPayloads('batch-test-job');
	expect($payloads)->toHaveCount(3)
		->and($payloads[0])->toBe(['user_id' => 1])
		->and($payloads[1])->toBe(['user_id' => 2])
		->and($payloads[2])->toBe(['user_id' => 3]);
});

test('only one trigger job is created per batch window', function () {
	queue(BatchTestJob::class, ['user_id' => 1]);
	queue(BatchTestJob::class, ['user_id' => 2]);

	expect(Queues::manager()->stats()['by_status']['pending'])->toBe(1);
});

test('trigger job is delayed by batch window', function () {
	$before = time();
	$jobId = queue(BatchTestJob::class, ['user_id' => 1]);
	$jobData = Queues::manager()->storage()->get($jobId);

	expect($jobData['available_at'])->toBeGreaterThanOrEqual($before + 5);
});

test('worker processes batch job with all accumulated payloads', function () {
	$storage = Queues::manager()->storage();

	$jobId = queue(BatchTestJob::class, ['user_id' => 1]);
	queue(BatchTestJob::class, ['user_id' => 2]);
	queue(BatchTestJob::class, ['user_id' => 3]);

	$storage->update($jobId, ['available_at' => time() - 1]);

	$job = Queues::manager()->pop('default');
	$worker = new Worker(Queues::manager());
	$result = $worker->processJob($job);

	expect($result->status())->toBe(\tobimori\Queues\JobStatus::COMPLETED)
		->and(BatchTestJob::$receivedPayloads)->toHaveCount(3)
		->and(BatchTestJob::$receivedPayloads[0])->toBe(['user_id' => 1])
		->and(BatchTestJob::$receivedPayloads[2])->toBe(['user_id' => 3]);

	// payloads persisted into job storage for retry safety
	$stored = $storage->get($jobId);
	expect($stored['payload'])->toHaveCount(3);
});

test('flush clears batch state', function () {
	$storage = Queues::manager()->storage();

	queue(BatchTestJob::class, ['user_id' => 1]);
	queue(BatchTestJob::class, ['user_id' => 2]);

	expect($storage->flushBatchPayloads('batch-test-job'))->toHaveCount(2);
	expect($storage->flushBatchPayloads('batch-test-job'))->toBe([]);
});

test('new dispatch after flush starts fresh batch', function () {
	$storage = Queues::manager()->storage();

	$id1 = queue(BatchTestJob::class, ['user_id' => 1]);
	$storage->flushBatchPayloads('batch-test-job');

	$id2 = queue(BatchTestJob::class, ['user_id' => 2]);
	expect($id2)->not->toBe($id1);

	$payloads = $storage->flushBatchPayloads('batch-test-job');
	expect($payloads)->toHaveCount(1)
		->and($payloads[0])->toBe(['user_id' => 2]);
});
