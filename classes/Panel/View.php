<?php

namespace tobimori\Queues\Panel;

use tobimori\Queues\Queues;

/**
 * Handles Panel view requests for the queues area
 *
 * @package tobimori\Queues
 */
class View
{
	/**
	 * Returns the view definition for a tab
	 */
	public static function tab(string $status = 'all'): array
	{
		$props = static::props($status);

		return [
			'component' => 'k-queues-view',
			'title' => t('queues.title'),
			'breadcrumb' => [
				[
					'label' => $status === 'all' ? t('queues.all') : t('queues.' . $status),
					'link' => $status === 'all' ? 'queues' : 'queues/' . $status
				]
			],
			'props' => $props
		];
	}

	/**
	 * Returns all props for the Panel view
	 */
	public static function props(string $status = 'all'): array
	{
		// Get query parameters
		$timeRange = get('timeRange', '24h');
		$page = (int) get('page', 1);
		$sortBy = get('sortBy', 'created_at');
		$sortOrder = get('sortOrder', 'desc');
		$jobType = get('jobType', '');
		$limit = 50;
		$offset = ($page - 1) * $limit;

		// Get jobs based on status
		if ($status === 'all') {
			$allJobs = static::getAllJobs();
		} else {
			$allJobs = Queues::manager()->getByStatus($status, 10000);
			// Add status to each job for consistency
			foreach ($allJobs as &$job) {
				$job['status'] = $status;
			}
		}

		// Get unique job types for filter (before filtering)
		$jobTypes = static::getJobTypes($allJobs);

		// Apply filters
		$filteredJobs = static::filterByTimeRange($allJobs, $timeRange);
		if ($jobType) {
			$filteredJobs = static::filterByJobType($filteredJobs, $jobType);
		}

		// Sort jobs
		$filteredJobs = static::sortJobs($filteredJobs, $sortBy, $sortOrder);

		// Paginate
		$total = count($filteredJobs);
		$jobs = array_slice($filteredJobs, $offset, $limit);

		// Get statistics
		$stats = static::getFilteredStats($timeRange, $jobType);

		return [
			'statistics' => $stats,
			'jobs' => $jobs,
			'total' => $total,
			'status' => $status,
			'page' => $page,
			'timeRange' => $timeRange,
			'sortBy' => $sortBy,
			'sortOrder' => $sortOrder,
			'jobType' => $jobType,
			'jobTypes' => $jobTypes
		];
	}

	/**
	 * Get all jobs from all statuses
	 */
	protected static function getAllJobs(): array
	{
		$allJobs = [];
		foreach (['pending', 'running', 'completed', 'failed'] as $status) {
			$statusJobs = Queues::manager()->getByStatus($status, 10000);
			foreach ($statusJobs as &$job) {
				$job['status'] = $status;
			}
			$allJobs = array_merge($allJobs, $statusJobs);
		}
		return $allJobs;
	}

	/**
	 * Filter jobs by time range
	 */
	protected static function filterByTimeRange(array $jobs, string $timeRange): array
	{
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

		return array_filter($jobs, function ($job) use ($cutoff) {
			return ($job['created_at'] ?? 0) >= $cutoff;
		});
	}

	/**
	 * Filter jobs by job type
	 */
	protected static function filterByJobType(array $jobs, string $jobType): array
	{
		if (!$jobType) {
			return $jobs;
		}

		return array_filter($jobs, function ($job) use ($jobType) {
			return $job['type'] === $jobType;
		});
	}

	/**
	 * Sort jobs
	 */
	protected static function sortJobs(array $jobs, string $sortBy, string $sortOrder): array
	{
		usort($jobs, function ($a, $b) use ($sortBy, $sortOrder) {
			$aVal = $a[$sortBy] ?? 0;
			$bVal = $b[$sortBy] ?? 0;

			if ($sortOrder === 'asc') {
				return $aVal <=> $bVal;
			} else {
				return $bVal <=> $aVal;
			}
		});

		return $jobs;
	}

	/**
	 * Get unique job types from jobs
	 */
	protected static function getJobTypes(array $jobs): array
	{
		$jobTypesMap = [];
		foreach ($jobs as $job) {
			$type = $job['type'];
			$name = $job['name'] ?? $job['type'];
			$jobTypesMap[$type] = $name;
		}

		$jobTypes = [];
		foreach ($jobTypesMap as $type => $name) {
			$jobTypes[] = ['value' => $type, 'label' => $name];
		}

		usort($jobTypes, function ($a, $b) {
			return strcasecmp($a['label'], $b['label']);
		});

		return $jobTypes;
	}

	/**
	 * Get filtered statistics
	 */
	protected static function getFilteredStats(string $timeRange, string $jobType = ''): array
	{
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
				$jobs = static::filterByTimeRange($jobs, $timeRange);
			}

			if ($jobType) {
				$jobs = static::filterByJobType($jobs, $jobType);
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
	}
}
