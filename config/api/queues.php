<?php

use Kirby\Cms\App;
use tobimori\Queues\JobStatus;
use tobimori\Queues\Queues;

/**
 * Queues API routes
 */
return [
	'routes' => [
		[
			'pattern' => 'queues/stats',
			'method' => 'GET',
			'action' => function () {
				return Queues::manager()->stats();
			}
		],
		[
			'pattern' => 'queues/jobs',
			'method' => 'GET',
			'action' => function () {
				$status = $this->requestQuery('status', JobStatus::PENDING->value);
				$limit = (int) $this->requestQuery('limit', 50);
				$offset = (int) $this->requestQuery('offset', 0);

				return [
					'jobs' => Queues::manager()->getByStatus($status, $limit, $offset),
					'total' => count(Queues::manager()->getByStatus($status, 1000))
				];
			}
		],
		[
			'pattern' => 'queues/jobs/(:any)',
			'method' => 'GET',
			'action' => function (string $id) {
				$job = Queues::manager()->find($id);

				if ($job === null) {
					throw new \Kirby\Exception\NotFoundException('Job not found');
				}

				return $job->toArray();
			}
		],
		[
			'pattern' => 'queues/jobs/(:any)',
			'method' => 'DELETE',
			'action' => function (string $id) {
				Queues::manager()->delete($id);
				return ['success' => true];
			}
		],
		[
			'pattern' => 'queues/jobs/(:any)/retry',
			'method' => 'POST',
			'action' => function (string $id) {
				$newId = Queues::manager()->retry($id);
				return ['success' => true, 'newId' => $newId];
			}
		],
		[
			'pattern' => 'queues/jobs',
			'method' => 'POST',
			'action' => function () {
				$type = $this->requestBody('type');
				$payload = $this->requestBody('payload', []);
				$queue = $this->requestBody('queue');

				try {
					$id = Queues::manager()->push($type, $payload, $queue);
					return ['success' => true, 'id' => $id];
				} catch (\Exception $e) {
					throw new \Kirby\Exception\Exception($e->getMessage());
				}
			}
		],
		[
			'pattern' => 'queues/scheduled',
			'method' => 'GET',
			'action' => function () {
				return Queues::scheduler()->all();
			}
		],
		[
			'pattern' => 'queues/scheduled/(:any)',
			'method' => 'DELETE',
			'action' => function (string $id) {
				Queues::scheduler()->unschedule($id);
				return ['success' => true];
			}
		],
		[
			'pattern' => 'queues/clear',
			'method' => 'POST',
			'action' => function () {
				$completedHours = $this->requestBody('completedHours');
				$failedHours = $this->requestBody('failedHours');

				$cleared = Queues::manager()->clear($completedHours, $failedHours);

				return ['success' => true, 'cleared' => $cleared];
			}
		]
	]
];