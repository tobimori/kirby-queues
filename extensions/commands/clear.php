<?php

use Kirby\CLI\CLI;
use tobimori\Queues\Queues;

return [
	'description' => 'Clear old completed and failed jobs',
	'args' => [
		'completed' => [
			'description' => 'Hours to keep completed jobs',
			'longPrefix' => 'completed',
			'defaultValue' => null,
			'castTo' => 'int'
		],
		'failed' => [
			'description' => 'Hours to keep failed jobs',
			'longPrefix' => 'failed',
			'defaultValue' => null,
			'castTo' => 'int'
		]
	],
	'command' => function (CLI $cli) {
		$completedHours = $cli->arg('completed');
		$failedHours = $cli->arg('failed');

		$cli->br();
		$cli->bold()->out('🧹 Clearing old jobs');
		$cli->br();

		if ($completedHours !== null) {
			$cli->padding(30)->char('.')->label('Completed jobs older than')->result("{$completedHours}h");
		}

		if ($failedHours !== null) {
			$cli->padding(30)->char('.')->label('Failed jobs older than')->result("{$failedHours}h");
		}

		$cli->br();

		try {
			$cleared = Queues::manager()->clear(
				$completedHours !== null ? (int) $completedHours : null,
				$failedHours !== null ? (int) $failedHours : null
			);

			$cli->green()->bold()->out("✓ Cleared {$cleared} job(s)");
		} catch (\Exception $e) {
			$cli->red()->bold()->out('✗ Failed to clear jobs');
			$cli->tab()->out($e->getMessage());
		}
	}
];
