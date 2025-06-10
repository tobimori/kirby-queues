<?php

use Kirby\CLI\CLI;
use tobimori\Queues\Queues;

return [
	'description' => 'List all scheduled jobs',
	'command' => function (CLI $cli) {
		$scheduler = Queues::scheduler();
		$scheduled = $scheduler->all();

		if (empty($scheduled)) {
			$cli->info('No scheduled jobs found');
			return;
		}

		$cli->br();
		$cli->out('Scheduled Jobs:', 'comment');
		$cli->br();

		foreach ($scheduled as $schedule) {
			$cli->out("Job: {$schedule['job']}", 'info');
			$cli->out("  Expression: {$schedule['expression']}");
			$cli->out("  Timezone: {$schedule['timezone']}");

			if ($schedule['lastRun']) {
				$cli->out("  Last run: " . date('Y-m-d H:i:s', $schedule['lastRun']));
			}

			if ($schedule['nextRun']) {
				$cli->out("  Next run: " . date('Y-m-d H:i:s', $schedule['nextRun']));
			}

			if (!empty($schedule['options']['queue'])) {
				$cli->out("  Queue: {$schedule['options']['queue']}");
			}

			$cli->br();
		}
	}
];
