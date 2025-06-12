<?php

namespace tobimori\Queues\Tests\Jobs;

use tobimori\Queues\Job;

class TestJob extends Job
{
	public function handle(): void
	{
		// Simple test job that does nothing
	}

	public function type(): string
	{
		return 'test-job';
	}

	public function name(): string
	{
		return 'Test Job';
	}
}
