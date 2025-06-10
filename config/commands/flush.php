<?php

use Kirby\CLI\CLI;
use tobimori\Queues\Queues;

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
			$cli->confirmToContinue('This will permanently delete all jobs from all queues. Continue?');
		}

		// Get the cache instance
		$cache = $cli->kirby()->cache('tobimori.queues');

		// Clear all cache entries
		$cache->flush();

		$cli->success('All queues have been flushed.');

		// Show empty stats
		$stats = Queues::manager()->stats();
		$cli->br();
		$cli->out('Queue Statistics:');
		$cli->out("Total jobs: " . $stats['total']);
	}
];
