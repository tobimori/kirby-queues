<?php

use Kirby\CLI\CLI;
use tobimori\Queues\Queues;

return [
	'description' => 'Clear old completed and failed jobs',
	'options' => [
		'--completed' => [
			'description' => 'Hours to keep completed jobs',
			'defaultValue' => null
		],
		'--failed' => [
			'description' => 'Hours to keep failed jobs',
			'defaultValue' => null
		]
	],
	'command' => function (CLI $cli) {
		$manager = Queues::manager();

		$completedHours = $cli->option('completed');
		$failedHours = $cli->option('failed');

		// use configured defaults if not specified
		$completedHours = $completedHours !== null ? (int) $completedHours : null;
		$failedHours = $failedHours !== null ? (int) $failedHours : null;

		$cli->out('Clearing old jobs...');

		try {
			$cleared = $manager->clear($completedHours, $failedHours);
			$cli->success("Cleared {$cleared} job(s)");
		} catch (\Exception $e) {
			$cli->error("Failed to clear jobs: " . $e->getMessage());
		}
	}
];
