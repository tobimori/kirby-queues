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

		$cli->info("Creating {$count} example jobs...");
		$cli->br();

		$jobIds = [];

		// Create regular jobs
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

			$jobIds[] = $jobId;
			$cli->out("✓ Created job {$jobId} in '{$queue}' queue");
		}

		// Create a delayed job
		$delayedJobId = Queues::later(10, ExampleJob::class, [
			'message' => 'Delayed example job',
			'duration' => 3
		]);
		$cli->out("✓ Created delayed job {$delayedJobId} (will run in 10 seconds)");

		// Create a failing job
		$failingJobId = Queues::push(FailingExampleJob::class);
		$cli->out("✓ Created failing job {$failingJobId} (will retry 2 times)");

		$cli->br();

		// Show statistics
		$stats = Queues::manager()->stats();
		$cli->success("Queue Statistics:");
		$cli->out("Total jobs: " . $stats['total']);
		$cli->out("By status:");
		foreach ($stats['by_status'] as $status => $count) {
			$cli->out("  {$status}: {$count}");
		}
		$cli->out("By queue:");
		foreach ($stats['by_queue'] as $queue => $count) {
			$cli->out("  {$queue}: {$count}");
		}

		$cli->br();
		$cli->info("To process these jobs, run:");
		$cli->out("  kirby queues:work");
		$cli->br();
		$cli->info("To process jobs from a specific queue:");
		$cli->out("  kirby queues:work high");
		$cli->br();
		$cli->info("To process only one job:");
		$cli->out("  kirby queues:work --once");
		$cli->br();
		$cli->info("To monitor queue status:");
		$cli->out("  kirby queues:status");
	}
];
