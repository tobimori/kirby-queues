<?php

use Kirby\CLI\CLI;
use tobimori\Queues\Queues;

return [
	'description' => 'Show queue status and statistics',
	'command' => function (CLI $cli) {
		$manager = Queues::manager();
		$stats = $manager->stats();

		$cli->br();
		$cli->bold()->out('📊 Queue Statistics');
		$cli->br();

		$padding = $cli->padding(25)->char('.');
		$padding->label('Total jobs')->result($stats['total']);
		$cli->br();

		if (!empty($stats['by_status']) && is_array($stats['by_status'])) {
			$cli->bold()->out('Job Status:');
			$statusData = [];
			foreach ($stats['by_status'] as $status => $count) {
				$statusData[] = [
					'Status' => ucfirst($status),
					'Count' => $count,
					'Percentage' => $stats['total'] > 0 ? round(($count / $stats['total']) * 100, 1) . '%' : '0%'
				];
			}
			$cli->table($statusData);
			$cli->br();
		}

		if (!empty($stats['by_queue']) && is_array($stats['by_queue'])) {
			$cli->bold()->out('By Queue:');
			$queueData = [];
			foreach ($stats['by_queue'] as $queue => $count) {
				$queueData[] = [
					'Queue' => $queue,
					'Jobs' => $count
				];
			}
			$cli->table($queueData);
			$cli->br();
		}

		$scheduled = $manager->scheduler()->all();
		if (!empty($scheduled)) {
			$cli->bold()->out('⏰ Scheduled Jobs:');
			$scheduledData = [];
			foreach ($scheduled as $schedule) {
				$nextRun = $schedule['nextRun'] ? date('Y-m-d H:i:s', $schedule['nextRun']) : 'N/A';
				$scheduledData[] = [
					'Job' => $schedule['job'],
					'Schedule' => $schedule['expression'],
					'Next Run' => $nextRun
				];
			}
			$cli->table($scheduledData);
		}
	}
];
