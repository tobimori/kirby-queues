<?php

return [
	'queues:work' => require __DIR__ . '/commands/work.php',
	'queues:status' => require __DIR__ . '/commands/status.php',
	'queues:retry' => require __DIR__ . '/commands/retry.php',
	'queues:clear' => require __DIR__ . '/commands/clear.php',
	'queues:schedule' => require __DIR__ . '/commands/schedule.php',
	'queues:example' => require __DIR__ . '/commands/example.php',
	'queues:flush' => require __DIR__ . '/commands/flush.php'
];