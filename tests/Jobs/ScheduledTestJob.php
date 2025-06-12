<?php

namespace tobimori\Queues\Tests\Jobs;

use tobimori\Queues\Job;

class ScheduledTestJob extends Job
{
	public function type(): string
	{
		return 'test:scheduled';
	}

	public function name(): string
	{
		return 'Scheduled Test Job';
	}

	public function handle(): void
	{
		$this->log('info', 'Running scheduled test job');

		if (isset($this->payload['value'])) {
			$this->log('info', 'Processing value: ' . $this->payload['value']);
		}
	}
}
