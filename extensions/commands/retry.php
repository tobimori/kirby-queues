<?php

use Kirby\CLI\CLI;
use tobimori\Queues\JobStatus;
use tobimori\Queues\Queues;

return [
	'description' => 'Retry failed jobs',
	'args' => [
		'id' => [
			'description' => 'Job ID to retry (or "all" for all failed jobs)',
			'defaultValue' => null
		]
	],
	'command' => function (CLI $cli, ?string $id = null) {
		if ($id === null) {
			$cli->error('Please provide a job ID or "all" to retry all failed jobs');
			return;
		}

		$manager = Queues::manager();

		if ($id === 'all') {
			// retry all failed jobs
			$failed = $manager->getByStatus(JobStatus::FAILED, 1000);

			if (empty($failed)) {
				$cli->info('No failed jobs to retry');
				return;
			}

			$count = 0;
			foreach ($failed as $job) {
				try {
					$manager->retry($job['id']);
					$count++;
				} catch (\Exception $e) {
					$cli->error("Failed to retry job {$job['id']}: " . $e->getMessage());
				}
			}

			$cli->success("Retried {$count} failed job(s)");
		} else {
			// retry specific job
			try {
				$newId = $manager->retry($id);
				$cli->success("Job {$id} queued for retry with new ID: {$newId}");
			} catch (\Exception $e) {
				$cli->error("Failed to retry job: " . $e->getMessage());
			}
		}
	}
];
