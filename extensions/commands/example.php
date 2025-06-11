<?php

use Kirby\CLI\CLI;
use tobimori\Queues\Queues;
use tobimori\Queues\Examples\ExampleJob;
use tobimori\Queues\Examples\FailingExampleJob;

return [
	'description' => 'Add example jobs to the queue for testing',
	'args' => [
		'count' => [
			'description' => 'Number of example jobs to create',
			'defaultValue' => 5,
			'castTo' => 'int'
		]
	],
	'command' => function (CLI $cli) {
		$count = $cli->arg('count') ?? 5;

		$cli->br();
		$cli->bold()->out("🚀 Creating {$count} example jobs");
		$cli->br();

		$createdJobs = [];

		for ($i = 1; $i <= $count; $i++) {
			$queue = match ($i % 3) {
				0 => 'high',
				1 => 'default',
				2 => 'low'
			};

			$jobId = Queues::push(ExampleJob::class, [
				'message' => "Example job #{$i}",
				'duration' => rand(2, 5)
			], $queue);

			$createdJobs[] = [
				'ID' => substr($jobId, 0, 8) . '...',
				'Type' => 'Regular',
				'Queue' => $queue,
				'Status' => 'pending'
			];
		}

		$delayedJobId = Queues::later(10, ExampleJob::class, [
			'message' => 'Delayed example job',
			'duration' => 3
		]);
		
		$createdJobs[] = [
			'ID' => substr($delayedJobId, 0, 8) . '...',
			'Type' => 'Delayed (10s)',
			'Queue' => 'default',
			'Status' => 'pending'
		];

		$failingJobId = Queues::push(FailingExampleJob::class);
		
		$createdJobs[] = [
			'ID' => substr($failingJobId, 0, 8) . '...',
			'Type' => 'Failing',
			'Queue' => 'default',
			'Status' => 'pending'
		];

		$cli->table($createdJobs);
		$cli->br();

		$stats = Queues::manager()->stats();
		$cli->bold()->green()->out('✓ Jobs created successfully!');
		$cli->br();
		
		$padding = $cli->padding(20)->char('.');
		$padding->label('Total jobs')->result($stats['total']);
		$cli->br();

		$cli->bold()->out('📝 Next steps:');
		$cli->br();
		
		$commands = [
			['Command' => 'kirby queues:work', 'Description' => 'Process all jobs'],
			['Command' => 'kirby queues:work high', 'Description' => 'Process high priority queue'],
			['Command' => 'kirby queues:work --once', 'Description' => 'Process single job'],
			['Command' => 'kirby queues:status', 'Description' => 'Monitor queue status']
		];
		
		$cli->table($commands);
	}
];
