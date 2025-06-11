<?php

use Kirby\Cms\App;
use tobimori\Queues\Queues;

/**
 * Queues panel area
 */
return [
	'queues' => function () {
		$filterByTimeRange = function($jobs, $timeRange) {
			if (!$timeRange || $timeRange === 'all') {
				return $jobs;
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
			
			return array_filter($jobs, function($job) use ($cutoff) {
				return ($job['created_at'] ?? 0) >= $cutoff;
			});
		};
		
		$getFilteredStats = function($timeRange, $jobType = '') use ($filterByTimeRange) {
			if (!$timeRange && !$jobType) {
				return Queues::manager()->stats();
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
				
				if ($timeRange && $timeRange !== 'all') {
					$jobs = $filterByTimeRange($jobs, $timeRange);
				}
				
				if ($jobType) {
					$jobs = array_filter($jobs, function($job) use ($jobType) {
						return $job['type'] === $jobType;
					});
				}
				
				$count = count($jobs);
				$stats['by_status'][$status] = $count;
				$stats['total'] += $count;
				
				foreach ($jobs as $job) {
					$queue = $job['queue'] ?? 'default';
					if (!isset($stats['by_queue'][$queue])) {
						$stats['by_queue'][$queue] = 0;
					}
					$stats['by_queue'][$queue]++;
				}
			}
			
			return $stats;
		};
		
		return [
			'label' => t('queues.title'),
			'icon' => 'layers',
			'menu' => true,
			'link' => 'queues',
			'views' => [
				[
					'pattern' => 'queues',
					'action' => function () use ($filterByTimeRange, $getFilteredStats) {
						$timeRange = get('timeRange', '24h');
						$page = (int) get('page', 1);
						$sortBy = get('sortBy', 'created_at');
						$sortOrder = get('sortOrder', 'desc');
						$jobType = get('jobType', '');
						$limit = 50;
						$offset = ($page - 1) * $limit;
						
						// get all jobs from all statuses
						$allJobs = [];
						foreach (['pending', 'running', 'completed', 'failed'] as $status) {
							$statusJobs = Queues::manager()->getByStatus($status, 10000);
							foreach ($statusJobs as &$job) {
								$job['status'] = $status;
							}
							$allJobs = array_merge($allJobs, $statusJobs);
						}
						
						// filter by time range
						$filteredJobs = $filterByTimeRange($allJobs, $timeRange);
						
						// filter by job type if specified
						if ($jobType) {
							$filteredJobs = array_filter($filteredJobs, function($job) use ($jobType) {
								return $job['type'] === $jobType;
							});
						}
						
						// sort jobs
						usort($filteredJobs, function($a, $b) use ($sortBy, $sortOrder) {
							$aVal = $a[$sortBy] ?? 0;
							$bVal = $b[$sortBy] ?? 0;
							
							if ($sortOrder === 'asc') {
								return $aVal <=> $bVal;
							} else {
								return $bVal <=> $aVal;
							}
						});
						
						// get unique job types for filter
						$jobTypesMap = [];
						foreach ($allJobs as $job) {
							$type = $job['type'];
							$name = $job['name'] ?? $job['type'];
							$jobTypesMap[$type] = $name;
						}
						$jobTypes = [];
						foreach ($jobTypesMap as $type => $name) {
							$jobTypes[] = ['value' => $type, 'label' => $name];
						}
						usort($jobTypes, function($a, $b) {
							return strcasecmp($a['label'], $b['label']);
						});
						
						$total = count($filteredJobs);
						$jobs = array_slice($filteredJobs, $offset, $limit);
						$stats = $getFilteredStats($timeRange, $jobType);

						return [
							'component' => 'k-queues-view',
							'title' => t('queues.title'),
							'breadcrumb' => [
								[
									'label' => t('queues.all'),
									'link' => 'queues'
								]
							],
							'props' => [
								'statistics' => $stats,
								'jobs' => $jobs,
								'total' => $total,
								'status' => 'all',
								'page' => $page,
								'timeRange' => $timeRange,
								'sortBy' => $sortBy,
								'sortOrder' => $sortOrder,
								'jobType' => $jobType,
								'jobTypes' => $jobTypes
							]
						];
					}
				],
				[
					'pattern' => 'queues/pending',
					'action' => function () use ($filterByTimeRange, $getFilteredStats) {
						$timeRange = get('timeRange', '24h');
						$page = (int) get('page', 1);
						$sortBy = get('sortBy', 'created_at');
						$sortOrder = get('sortOrder', 'desc');
						$jobType = get('jobType', '');
						$limit = 50;
						$offset = ($page - 1) * $limit;
						
						$allJobs = Queues::manager()->getByStatus('pending', 10000);
						
						// get all job types for filter
						$jobTypesMap = [];
						foreach ($allJobs as $job) {
							$type = $job['type'];
							$name = $job['name'] ?? $job['type'];
							$jobTypesMap[$type] = $name;
						}
						$allTypes = [];
						foreach ($jobTypesMap as $type => $name) {
							$allTypes[] = ['value' => $type, 'label' => $name];
						}
						usort($allTypes, function($a, $b) {
							return strcasecmp($a['label'], $b['label']);
						});
						
						$filteredJobs = $filterByTimeRange($allJobs, $timeRange);
						
						// filter by job type if specified
						if ($jobType) {
							$filteredJobs = array_filter($filteredJobs, function($job) use ($jobType) {
								return $job['type'] === $jobType;
							});
						}
						
						// sort jobs
						usort($filteredJobs, function($a, $b) use ($sortBy, $sortOrder) {
							$aVal = $a[$sortBy] ?? 0;
							$bVal = $b[$sortBy] ?? 0;
							
							if ($sortOrder === 'asc') {
								return $aVal <=> $bVal;
							} else {
								return $bVal <=> $aVal;
							}
						});
						
						$total = count($filteredJobs);
						$jobs = array_slice($filteredJobs, $offset, $limit);
						$stats = $getFilteredStats($timeRange, $jobType);

						return [
							'component' => 'k-queues-view',
							'title' => t('queues.title'),
							'breadcrumb' => [
								[
									'label' => t('queues.status.pending'),
									'link' => 'queues/pending'
								]
							],
							'props' => [
								'statistics' => $stats,
								'jobs' => $jobs,
								'total' => $total,
								'status' => 'pending',
								'page' => $page,
								'timeRange' => $timeRange,
								'sortBy' => $sortBy,
								'sortOrder' => $sortOrder,
								'jobType' => $jobType,
								'jobTypes' => $allTypes
							]
						];
					}
				],
				[
					'pattern' => 'queues/completed',
					'action' => function () use ($filterByTimeRange, $getFilteredStats) {
						$timeRange = get('timeRange', '24h');
						$page = (int) get('page', 1);
						$sortBy = get('sortBy', 'created_at');
						$sortOrder = get('sortOrder', 'desc');
						$jobType = get('jobType', '');
						$limit = 50;
						$offset = ($page - 1) * $limit;
						
						$allJobs = Queues::manager()->getByStatus('completed', 10000);
						
						// get all job types for filter
						$jobTypesMap = [];
						foreach ($allJobs as $job) {
							$type = $job['type'];
							$name = $job['name'] ?? $job['type'];
							$jobTypesMap[$type] = $name;
						}
						$allTypes = [];
						foreach ($jobTypesMap as $type => $name) {
							$allTypes[] = ['value' => $type, 'label' => $name];
						}
						usort($allTypes, function($a, $b) {
							return strcasecmp($a['label'], $b['label']);
						});
						
						$filteredJobs = $filterByTimeRange($allJobs, $timeRange);
						
						// filter by job type if specified
						if ($jobType) {
							$filteredJobs = array_filter($filteredJobs, function($job) use ($jobType) {
								return $job['type'] === $jobType;
							});
						}
						
						// sort jobs
						usort($filteredJobs, function($a, $b) use ($sortBy, $sortOrder) {
							$aVal = $a[$sortBy] ?? 0;
							$bVal = $b[$sortBy] ?? 0;
							
							if ($sortOrder === 'asc') {
								return $aVal <=> $bVal;
							} else {
								return $bVal <=> $aVal;
							}
						});
						
						$total = count($filteredJobs);
						$jobs = array_slice($filteredJobs, $offset, $limit);
						$stats = $getFilteredStats($timeRange, $jobType);

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
								'total' => $total,
								'status' => 'completed',
								'page' => $page,
								'timeRange' => $timeRange,
								'sortBy' => $sortBy,
								'sortOrder' => $sortOrder,
								'jobType' => $jobType,
								'jobTypes' => $allTypes
							]
						];
					}
				],
				[
					'pattern' => 'queues/running',
					'action' => function () use ($filterByTimeRange, $getFilteredStats) {
						$timeRange = get('timeRange', '24h');
						$page = (int) get('page', 1);
						$sortBy = get('sortBy', 'created_at');
						$sortOrder = get('sortOrder', 'desc');
						$jobType = get('jobType', '');
						$limit = 50;
						$offset = ($page - 1) * $limit;
						
						$allJobs = Queues::manager()->getByStatus('running', 10000);
						
						// get all job types for filter
						$jobTypesMap = [];
						foreach ($allJobs as $job) {
							$type = $job['type'];
							$name = $job['name'] ?? $job['type'];
							$jobTypesMap[$type] = $name;
						}
						$allTypes = [];
						foreach ($jobTypesMap as $type => $name) {
							$allTypes[] = ['value' => $type, 'label' => $name];
						}
						usort($allTypes, function($a, $b) {
							return strcasecmp($a['label'], $b['label']);
						});
						
						$filteredJobs = $filterByTimeRange($allJobs, $timeRange);
						
						// filter by job type if specified
						if ($jobType) {
							$filteredJobs = array_filter($filteredJobs, function($job) use ($jobType) {
								return $job['type'] === $jobType;
							});
						}
						
						// sort jobs
						usort($filteredJobs, function($a, $b) use ($sortBy, $sortOrder) {
							$aVal = $a[$sortBy] ?? 0;
							$bVal = $b[$sortBy] ?? 0;
							
							if ($sortOrder === 'asc') {
								return $aVal <=> $bVal;
							} else {
								return $bVal <=> $aVal;
							}
						});
						
						$total = count($filteredJobs);
						$jobs = array_slice($filteredJobs, $offset, $limit);
						$stats = $getFilteredStats($timeRange, $jobType);

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
								'total' => $total,
								'status' => 'running',
								'page' => $page,
								'timeRange' => $timeRange,
								'sortBy' => $sortBy,
								'sortOrder' => $sortOrder,
								'jobType' => $jobType,
								'jobTypes' => $allTypes
							]
						];
					}
				],
				[
					'pattern' => 'queues/failed',
					'action' => function () use ($filterByTimeRange, $getFilteredStats) {
						$timeRange = get('timeRange', '24h');
						$page = (int) get('page', 1);
						$sortBy = get('sortBy', 'created_at');
						$sortOrder = get('sortOrder', 'desc');
						$jobType = get('jobType', '');
						$limit = 50;
						$offset = ($page - 1) * $limit;
						
						$allJobs = Queues::manager()->getByStatus('failed', 10000);
						
						// get all job types for filter
						$jobTypesMap = [];
						foreach ($allJobs as $job) {
							$type = $job['type'];
							$name = $job['name'] ?? $job['type'];
							$jobTypesMap[$type] = $name;
						}
						$allTypes = [];
						foreach ($jobTypesMap as $type => $name) {
							$allTypes[] = ['value' => $type, 'label' => $name];
						}
						usort($allTypes, function($a, $b) {
							return strcasecmp($a['label'], $b['label']);
						});
						
						$filteredJobs = $filterByTimeRange($allJobs, $timeRange);
						
						// filter by job type if specified
						if ($jobType) {
							$filteredJobs = array_filter($filteredJobs, function($job) use ($jobType) {
								return $job['type'] === $jobType;
							});
						}
						
						// sort jobs
						usort($filteredJobs, function($a, $b) use ($sortBy, $sortOrder) {
							$aVal = $a[$sortBy] ?? 0;
							$bVal = $b[$sortBy] ?? 0;
							
							if ($sortOrder === 'asc') {
								return $aVal <=> $bVal;
							} else {
								return $bVal <=> $aVal;
							}
						});
						
						$total = count($filteredJobs);
						$jobs = array_slice($filteredJobs, $offset, $limit);
						$stats = $getFilteredStats($timeRange, $jobType);

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
								'total' => $total,
								'status' => 'failed',
								'page' => $page,
								'timeRange' => $timeRange,
								'sortBy' => $sortBy,
								'sortOrder' => $sortOrder,
								'jobType' => $jobType,
								'jobTypes' => $allTypes
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
