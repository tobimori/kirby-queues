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
		// Ensure queue system is initialized
		try {
			Queues::manager();
		} catch (\RuntimeException $e) {
			Queues::init();
		}

		// get arguments from CLI
		$queue = $cli->arg('queue');
		$once = $cli->isDefined('once');
		$timeout = $cli->arg('timeout');
		$memory = $cli->arg('memory');
		$sleep = $cli->arg('sleep');
		$tries = $cli->arg('tries');
		$maxJobs = $cli->arg('max-jobs');

		// parse queue names
		$queues = null;
		if (!empty($queue)) {
			$queues = array_map('trim', explode(',', $queue));
		}

		// build options from CLI arguments
		$options = [];

		// Only add options if they have actual values
		if (!empty($timeout) && is_numeric($timeout)) {
			$options['timeout'] = (int)$timeout;
		}

		if (!empty($memory) && is_numeric($memory)) {
			$options['memory'] = (int)$memory;
		}

		if (!empty($sleep) && is_numeric($sleep)) {
			$options['sleep'] = (int)$sleep;
		}

		if (!empty($tries) && is_numeric($tries)) {
			$options['tries'] = (int)$tries;
		}

		if (!empty($maxJobs) && is_numeric($maxJobs)) {
			$options['maxJobs'] = (int)$maxJobs;
		}

		// create and start worker
		$worker = new Worker(null, $cli, $options);

		try {
			$worker->work($queues, $once);
		} catch (\Exception $e) {
			$cli->error("Worker error: " . $e->getMessage());
			exit(1);
		}
	}
];
