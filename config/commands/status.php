<?php

use Kirby\CLI\CLI;
use tobimori\Queues\Queues;

return [
	'description' => 'Show queue status and statistics',
	'command' => function (CLI $cli) {
		$manager = Queues::manager();
		$stats = $manager->stats();

		$cli->br();
		$cli->out('Queue Statistics:', 'comment');
		$cli->br();

		// overall stats
		$cli->out('Total jobs: ' . $stats['total']);
		$cli->br();

		// status breakdown
		$cli->out('By Status:', 'comment');
		foreach ($stats['by_status'] as $status => $count) {
			$cli->out("  {$status}: {$count}");
		}
		$cli->br();

		// queue breakdown
		if (!empty($stats['by_queue'])) {
			$cli->out('By Queue:', 'comment');
			foreach ($stats['by_queue'] as $queue => $count) {
				$cli->out("  {$queue}: {$count}");
			}
			$cli->br();
		}

		// scheduled jobs
		$scheduled = $manager->scheduler()->all();
		if (!empty($scheduled)) {
			$cli->out('Scheduled Jobs:', 'comment');
			foreach ($scheduled as $schedule) {
				$nextRun = $schedule['nextRun'] ? date('Y-m-d H:i:s', $schedule['nextRun']) : 'N/A';
				$cli->out("  {$schedule['job']} ({$schedule['expression']}) - Next: {$nextRun}");
			}
			$cli->br();
		}
	}
];
