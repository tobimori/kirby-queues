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
	'command' => function (CLI $cli) {
		$id = $cli->arg('id');

		if ($id === null || $id === '') {
			$cli->br();
			$cli->red()->bold()->out('✗ Missing argument');
			$cli->out('Please provide a job ID or "all" to retry all failed jobs');
			return;
		}

		$manager = Queues::manager();

		$cli->br();
		$cli->bold()->out('🔄 Retrying failed jobs');
		$cli->br();

		if ($id === 'all') {
			$failed = $manager->getByStatus(JobStatus::FAILED, 1000);

			if (empty($failed)) {
				$cli->yellow()->out('No failed jobs to retry');
				return;
			}

			$results = [];
			foreach ($failed as $job) {
				try {
					$newId = $manager->retry($job['id']);
					$results[] = [
						'Original ID' => substr($job['id'], 0, 8) . '...',
						'Job' => $job['handler'] ?? 'Unknown',
						'Status' => '✓ Retried',
						'New ID' => substr($newId, 0, 8) . '...'
					];
				} catch (\Exception $e) {
					$results[] = [
						'Original ID' => substr($job['id'], 0, 8) . '...',
						'Job' => $job['handler'] ?? 'Unknown',
						'Status' => '✗ Failed',
						'New ID' => $e->getMessage()
					];
				}
			}

			$cli->table($results);
			$cli->br();

			$successCount = count(array_filter($results, fn ($r) => $r['Status'] === '✓ Retried'));
			$cli->green()->bold()->out("✓ Retried {$successCount} of " . count($failed) . " job(s)");
		} else {
			try {
				$job = $manager->find($id);
				if (!$job) {
					$cli->red()->bold()->out('✗ Job not found');
					return;
				}

				$newId = $manager->retry($id);

				$retryData = [
					['Field' => 'Original ID', 'Value' => $id],
					['Field' => 'New ID', 'Value' => $newId],
					['Field' => 'Job Type', 'Value' => $job->type()],
					['Field' => 'Queue', 'Value' => $job->options()['queue'] ?? 'default']
				];

				$cli->table($retryData);
				$cli->br();
				$cli->green()->bold()->out('✓ Job queued for retry');
			} catch (\Exception $e) {
				$cli->red()->bold()->out('✗ Failed to retry job');
				$cli->tab()->out($e->getMessage());
			}
		}
	}
];
