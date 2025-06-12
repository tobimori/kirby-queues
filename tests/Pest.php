<?php

use tobimori\Queues\Tests\Jobs\ProcessDataJob;
use tobimori\Queues\Queues;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses()->beforeEach(function () {
	// Initialize queue system
	Queues::init();

	// Clear all jobs from storage
	$storage = Queues::manager()->storage();
	foreach (['pending', 'processing', 'completed', 'failed'] as $status) {
		$jobs = $storage->getByStatus($status, 1000);
		foreach ($jobs as $job) {
			$storage->delete($job['id']);
		}
	}

	// Clear scheduled jobs
	$scheduler = Queues::scheduler();
	$scheduled = $scheduler->all();
	foreach ($scheduled as $id => $job) {
		$scheduler->unschedule($id);
	}

	ProcessDataJob::reset();
})->in('.');
