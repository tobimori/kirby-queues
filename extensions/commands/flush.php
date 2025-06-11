<?php

use Kirby\CLI\CLI;

return [
	'description' => 'Clear all jobs from all queues',
	'args' => [
		'force' => [
			'description' => 'Force flush without confirmation',
			'prefix' => 'f',
			'longPrefix' => 'force',
			'noValue' => true
		]
	],
	'command' => function (CLI $cli) {
		$force = $cli->isDefined('force');

		if (!$force) {
			$cli->br();
			$cli->bold()->yellow()->out('⚠️  Warning');
			$cli->out('This will permanently delete all jobs from all queues.');
			$cli->br();
			$cli->confirmToContinue('Are you sure you want to continue?');
		}

		$cli->br();
		$cli->bold()->out('🗑️  Flushing all queues...');

		$cache = $cli->kirby()->cache('tobimori.queues');
		$cache->flush();

		$cli->br();
		$cli->green()->bold()->out('✓ All queues have been flushed');
	}
];
