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
				$timeRange = $this->requestQuery('timeRange', null);
				
				if (!$timeRange || $timeRange === 'all') {
					return Queues::manager()->stats();
				}
				
				$now = time();
				$cutoff = $now;
				
				switch ($timeRange) {
					case '1h':
						$cutoff = $now - 3600;
						break;
					case '24h':
						$cutoff = $now - 86400;
						break;
					case '7d':
						$cutoff = $now - 604800;
						break;
					case '30d':
						$cutoff = $now - 2592000;
						break;
				}
				
				$stats = [
					'total' => 0,
					'by_status' => [
						'pending' => 0,
						'running' => 0,
						'completed' => 0,
						'failed' => 0
					],
					'by_queue' => []
				];
				
				foreach (['pending', 'running', 'completed', 'failed'] as $status) {
					$jobs = Queues::manager()->getByStatus($status, 10000);
					$filteredJobs = array_filter($jobs, function($job) use ($cutoff) {
						return ($job['created_at'] ?? 0) >= $cutoff;
					});
					$count = count($filteredJobs);
					$stats['by_status'][$status] = $count;
					$stats['total'] += $count;
					
					foreach ($filteredJobs as $job) {
						$queue = $job['queue'] ?? 'default';
						if (!isset($stats['by_queue'][$queue])) {
							$stats['by_queue'][$queue] = 0;
						}
						$stats['by_queue'][$queue]++;
					}
				}
				
				return $stats;
			}
		],
		[
			'pattern' => 'queues/jobs',
			'method' => 'GET',
			'action' => function () {
				$status = $this->requestQuery('status', JobStatus::PENDING->value);
				$limit = (int) $this->requestQuery('limit', 50);
				$offset = (int) $this->requestQuery('offset', 0);
				$timeRange = $this->requestQuery('timeRange', null);

				// Get all jobs for the status
				$allJobs = Queues::manager()->getByStatus($status, 10000);
				
				// Filter by time range if specified
				if ($timeRange && $timeRange !== 'all') {
					$now = time();
					$cutoff = $now;
					
					switch ($timeRange) {
						case '1h':
							$cutoff = $now - 3600;
							break;
						case '24h':
							$cutoff = $now - 86400;
							break;
						case '7d':
							$cutoff = $now - 604800;
							break;
						case '30d':
							$cutoff = $now - 2592000;
							break;
					}
					
					$allJobs = array_filter($allJobs, function($job) use ($cutoff) {
						return ($job['created_at'] ?? 0) >= $cutoff;
					});
				}
				
				// Apply pagination
				$total = count($allJobs);
				$jobs = array_slice($allJobs, $offset, $limit);

				return [
					'jobs' => $jobs,
					'total' => $total
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