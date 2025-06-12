<?php

namespace tobimori\Queues\Tests\Jobs;

use tobimori\Queues\Job;

class FailingTestJob extends Job
{
	public function handle(): void
	{
		throw new \Exception('Test job failed');
	}

	public function type(): string
	{
		return 'failing-test-job';
	}

	public function name(): string
	{
		return 'Failing Test Job';
	}
}
