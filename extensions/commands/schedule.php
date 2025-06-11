<?php

use Kirby\CLI\CLI;
use tobimori\Queues\Queues;

return [
	'description' => 'List all scheduled jobs',
	'command' => function (CLI $cli) {
		$scheduler = Queues::scheduler();
		$scheduled = $scheduler->all();

		$cli->br();
		$cli->bold()->out('⏰ Scheduled Jobs');
		$cli->br();

		if (empty($scheduled)) {
			$cli->yellow()->out('No scheduled jobs found');
			return;
		}

		$scheduleData = [];
		foreach ($scheduled as $schedule) {
			$lastRun = $schedule['lastRun'] ? date('Y-m-d H:i:s', $schedule['lastRun']) : 'Never';
			$nextRun = $schedule['nextRun'] ? date('Y-m-d H:i:s', $schedule['nextRun']) : 'N/A';
			$queue = $schedule['options']['queue'] ?? 'default';
			
			$scheduleData[] = [
				'Job' => $schedule['job'],
				'Schedule' => $schedule['expression'],
				'Queue' => $queue,
				'Last Run' => $lastRun,
				'Next Run' => $nextRun,
				'Timezone' => $schedule['timezone']
			];
		}

		$cli->table($scheduleData);
		$cli->br();

		$padding = $cli->padding(25)->char('.');
		$padding->label('Total scheduled jobs')->result(count($scheduled));
		
		$nextJob = null;
		$nextTime = PHP_INT_MAX;
		foreach ($scheduled as $schedule) {
			if ($schedule['nextRun'] && $schedule['nextRun'] < $nextTime) {
				$nextTime = $schedule['nextRun'];
				$nextJob = $schedule;
			}
		}
		
		if ($nextJob) {
			$cli->br();
			$cli->bold()->out('⏭️  Next job to run:');
			$cli->tab()->out($nextJob['job']);
			$cli->tab()->out('at ' . date('Y-m-d H:i:s', $nextJob['nextRun']));
		}
	}
];
