<?php

use Kirby\Cms\App;
use tobimori\Queues\Queues;

/**
 * Queues panel area
 */
return [
	'queues' => function () {
		return [
			'label' => t('queues.title'),
			'icon' => 'layers',
			'menu' => true,
			'link' => 'queues',
			'views' => [
				[
					'pattern' => 'queues',
					'action' => function () {
						$stats = Queues::manager()->stats();
						$jobs = Queues::manager()->getByStatus('pending', 50, 0);

						return [
							'component' => 'k-queues-view',
							'title' => t('queues.title'),
							'breadcrumb' => [
								[
									'label' => t('queues.status.pending'),
									'link' => 'queues'
								]
							],
							'props' => [
								'statistics' => $stats,
								'jobs' => $jobs,
								'total' => count(Queues::manager()->getByStatus('pending', 1000)),
								'status' => 'pending'
							]
						];
					}
				],
				[
					'pattern' => 'queues/completed',
					'action' => function () {
						$stats = Queues::manager()->stats();
						$jobs = Queues::manager()->getByStatus('completed', 50, 0);

						return [
							'component' => 'k-queues-view',
							'title' => t('queues.title'),
							'breadcrumb' => [
								[
									'label' => t('queues.status.completed'),
									'link' => 'queues/completed'
								]
							],
							'props' => [
								'statistics' => $stats,
								'jobs' => $jobs,
								'total' => count(Queues::manager()->getByStatus('completed', 1000)),
								'status' => 'completed'
							]
						];
					}
				],
				[
					'pattern' => 'queues/running',
					'action' => function () {
						$stats = Queues::manager()->stats();
						$jobs = Queues::manager()->getByStatus('running', 50, 0);

						return [
							'component' => 'k-queues-view',
							'title' => t('queues.title'),
							'breadcrumb' => [
								[
									'label' => t('queues.status.running'),
									'link' => 'queues/running'
								]
							],
							'props' => [
								'statistics' => $stats,
								'jobs' => $jobs,
								'total' => count(Queues::manager()->getByStatus('running', 1000)),
								'status' => 'running'
							]
						];
					}
				],
				[
					'pattern' => 'queues/failed',
					'action' => function () {
						$stats = Queues::manager()->stats();
						$jobs = Queues::manager()->getByStatus('failed', 50, 0);

						return [
							'component' => 'k-queues-view',
							'title' => t('queues.title'),
							'breadcrumb' => [
								[
									'label' => t('queues.status.failed'),
									'link' => 'queues/failed'
								]
							],
							'props' => [
								'statistics' => $stats,
								'jobs' => $jobs,
								'total' => count(Queues::manager()->getByStatus('failed', 1000)),
								'status' => 'failed'
							]
						];
					}
				],
				[
					'pattern' => 'queues/metrics',
					'action' => function () {
						return [
							'component' => 'k-queues-metrics-view',
							'title' => t('queues.title'),
							'breadcrumb' => [
								[
									'label' => t('queues.metrics'),
									'link' => 'queues/metrics'
								]
							],
							'props' => []
						];
					}
				]
			]
		];
	}
];
