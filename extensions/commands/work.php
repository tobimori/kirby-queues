<?php

use Kirby\CLI\CLI;
use tobimori\Queues\Worker\Worker;
use tobimori\Queues\Queues;

return [
	'description' => 'Process jobs from the queue',
	'args' => [
		'queue' => [
			'description' => 'Queue name(s) to process (comma-separated)',
			'defaultValue' => null,
			'required' => false
		],
		'once' => [
			'description' => 'Process only one job then exit',
			'prefix' => 'o',
			'longPrefix' => 'once',
			'noValue' => true
		],
		'timeout' => [
			'description' => 'Job timeout in seconds',
			'prefix' => 't',
			'longPrefix' => 'timeout',
			'castTo' => 'int'
		],
		'memory' => [
			'description' => 'Memory limit in MB',
			'prefix' => 'm',
			'longPrefix' => 'memory',
			'castTo' => 'int'
		],
		'sleep' => [
			'description' => 'Seconds to sleep between queue checks',
			'prefix' => 's',
			'longPrefix' => 'sleep',
			'castTo' => 'int',
			'defaultValue' => 5
		],
		'tries' => [
			'description' => 'Maximum attempts for failed jobs',
			'longPrefix' => 'tries',
			'castTo' => 'int'
		],
		'max-jobs' => [
			'description' => 'Maximum jobs to process before restart',
			'longPrefix' => 'max-jobs',
			'castTo' => 'int'
		]
	],
	'command' => function (CLI $cli) {
		$queues = $cli->arg('queue') ? array_map('trim', explode(',', $cli->arg('queue'))) : null;

		$worker = new Worker(null, $cli, array_filter([
			'timeout' => $cli->arg('timeout'),
			'memory' => $cli->arg('memory'),
			'sleep' => $cli->arg('sleep'),
			'tries' => $cli->arg('tries'),
			'maxJobs' => $cli->arg('max-jobs')
		]));

		try {
			$worker->work($queues, $cli->isDefined('once'));
		} catch (\Exception $e) {
			$cli->error("Worker error: " . $e->getMessage());
			exit(1);
		}
	}
];
