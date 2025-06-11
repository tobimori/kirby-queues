<?php

use Kirby\Cms\App;
use Kirby\Data\Json;
use tobimori\Queues\Queues;

@include_once __DIR__ . '/vendor/autoload.php';

App::plugin('tobimori/queues', [
	'options' => [
		'cache' => true,

		// queue configuration
		'queues' => ['default', 'high', 'low'],  // available queue names
		'default' => 'default',  // default queue name
		'connection' => [
			'driver' => 'cache',
			'cache' => 'tobimori.queues'
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
	'commands' => [
		'queues:work' => require __DIR__ . '/extensions/commands/work.php',
		'queues:status' => require __DIR__ . '/extensions/commands/status.php',
		'queues:retry' => require __DIR__ . '/extensions/commands/retry.php',
		'queues:clear' => require __DIR__ . '/extensions/commands/clear.php',
		'queues:schedule' => require __DIR__ . '/extensions/commands/schedule.php',
		'queues:flush' => require __DIR__ . '/extensions/commands/flush.php'
	],

	// panel areas
	'areas' => require __DIR__ . '/extensions/areas.php',

	// translations
	'translations' => [
		'en' => Json::read(__DIR__ . '/translations/en.json'),
		'de' => Json::read(__DIR__ . '/translations/de.json'),
		'fr' => Json::read(__DIR__ . '/translations/fr.json'),
		'cs' => Json::read(__DIR__ . '/translations/cs.json'),
		'nl' => Json::read(__DIR__ . '/translations/nl.json'),
		'it' => Json::read(__DIR__ . '/translations/it.json'),
		'es' => Json::read(__DIR__ . '/translations/es.json')
	],

	'hooks' => [
		'system.loadPlugins:after' => function () {
			// initialize queue manager
			Queues::init();
		}
	]
]);
