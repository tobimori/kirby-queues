<?php

use Kirby\Cms\App;
use Kirby\Data\Json;
use tobimori\Queues\Queues;

@include_once __DIR__ . '/vendor/autoload.php';

App::plugin('tobimori/queues', [
	'caches' => [
		'tobimori.queues' => true
	],
	'options' => [
		// Enable the plugin cache
		'cache' => true,

		// queue configuration
		'queues' => ['default', 'high', 'low'],  // Available queue names
		'default' => 'default',  // Default queue name
		'connection' => [
			'driver' => 'cache',
			'cache' => 'tobimori.queues'  // Use the plugin's cache namespace
		],

		// worker configuration
		'worker' => [
			'timeout' => 60,      // seconds
			'memory' => 128,      // MB
			'sleep' => 5,         // seconds between checks
			'tries' => 3,         // max attempts
			'backoff' => 60,      // seconds between retries
			'maxJobs' => 1000     // jobs before restart
		],

		// job retention
		'retention' => [
			'completed' => 24,    // hours
			'failed' => 168       // 7 days
		],

		// scheduling
		'schedule' => [
			'timezone' => 'UTC',
			'overlap' => false    // prevent overlapping
		]
	],

	// cli commands
	'commands' => require __DIR__ . '/config/commands.php',

	// api routes
	'api' => require __DIR__ . '/config/api.php',

	// panel areas
	'areas' => require __DIR__ . '/config/areas.php',

	// translations
	'translations' => [
		'en' => Json::read(__DIR__ . '/translations/en.json'),
		'de' => Json::read(__DIR__ . '/translations/de.json')
	],

	// hooks
	'hooks' => [
		'system.loadPlugins:after' => function () {
			// initialize queue manager
			Queues::init();

			// register example jobs (for demonstration purposes)
			Queues::register([
				\tobimori\Queues\Examples\ExampleJob::class,
				\tobimori\Queues\Examples\FailingExampleJob::class
			]);
		}
	]
]);
