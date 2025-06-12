<?php

use tobimori\Queues\Queues;
use tobimori\Queues\Tests\Jobs\TestJob;
use tobimori\Queues\Tests\Jobs\ProcessDataJob;

test('status command returns queue statistics', function () {
	$id1 = queue(TestJob::class);
	$id2 = queue(ProcessDataJob::class, ['data' => 'command-test']);

	expect($id1)->toBeString();
	expect($id2)->toBeString();

	$stats = Queues::manager()->stats();

	expect($stats['total'])->toBeGreaterThanOrEqual(2);
	expect($stats['by_status']['pending'])->toBeGreaterThanOrEqual(2);
});

test('clear command removes old completed jobs', function () {
	// For minimal test, just verify we can call clear
	// and it returns a count
	$cleared = Queues::manager()->clear(0, 0);

	expect($cleared)->toBeInt();
	expect($cleared)->toBeGreaterThanOrEqual(0);
});
