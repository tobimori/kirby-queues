<?php

use tobimori\Queues\Panel\View;

return [
	'queues' => fn () => [
		'label' => t('queues.title'),
		'icon' => 'layers',
		'menu' => true,
		'link' => 'queues',
		'views' => [
			[
				'pattern' => 'queues',
				'action' => fn () => View::tab('all')
			],
			[
				'pattern' => 'queues/pending',
				'action' => fn () => View::tab('pending')
			],
			[
				'pattern' => 'queues/completed',
				'action' => fn () => View::tab('completed')
			],
			[
				'pattern' => 'queues/running',
				'action' => fn () => View::tab('running')
			],
			[
				'pattern' => 'queues/failed',
				'action' => fn () => View::tab('failed')
			]
		],
	]
];
