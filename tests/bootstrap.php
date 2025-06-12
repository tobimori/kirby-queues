<?php

use Kirby\Cms\App;

const KIRBY_HELPER_DUMP = false;
const KIRBY_HELPER_E = false;

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/Jobs/TestJob.php';
require_once __DIR__ . '/Jobs/FailingTestJob.php';
require_once __DIR__ . '/Jobs/ProcessDataJob.php';

require_once __DIR__ . '/../index.php';

$app = new App([
	'roots' => [
		'index' => __DIR__,
		'base' => __DIR__,
	],
]);

// Register test job types
\tobimori\Queues\Queues::register(
	\tobimori\Queues\Tests\Jobs\TestJob::class,
	\tobimori\Queues\Tests\Jobs\FailingTestJob::class,
	\tobimori\Queues\Tests\Jobs\ProcessDataJob::class
);
