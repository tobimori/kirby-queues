<?php

use tobimori\Queues\Queues;
use tobimori\Queues\Tests\Jobs\ScheduledTestJob;

test('scheduler can schedule a job', function () {
	$scheduler = Queues::scheduler();

	$scheduler->schedule('* * * * *', ScheduledTestJob::class, ['value' => 'test']);

	$scheduled = $scheduler->all();
	expect($scheduled)->toHaveCount(1);

	$schedule = array_values($scheduled)[0];
	expect($schedule['expression'])->toBe('* * * * *');
	expect($schedule['job'])->toBe('test:scheduled');
	expect($schedule['payload'])->toBe(['value' => 'test']);
});

test('scheduler validates cron expressions', function () {
	$scheduler = Queues::scheduler();

	expect(fn () => $scheduler->schedule('invalid', ScheduledTestJob::class))
		->toThrow(\Kirby\Exception\InvalidArgumentException::class, 'Invalid cron expression');
});

test('scheduler can unschedule a job', function () {
	$scheduler = Queues::scheduler();

	$scheduler->schedule('0 * * * *', ScheduledTestJob::class, ['task' => 'hourly']);

	$scheduled = $scheduler->all();
	$id = array_keys($scheduled)[0];

	$scheduler->unschedule($id);

	expect($scheduler->all())->toBeEmpty();
});

test('scheduler runs due jobs', function () {
	$scheduler = Queues::scheduler();
	$manager = Queues::manager();

	$storage = $manager->storage();
	foreach (['pending', 'processing', 'completed', 'failed'] as $status) {
		$jobs = $storage->getByStatus($status, 1000);
		foreach ($jobs as $job) {
			$storage->delete($job['id']);
		}
	}

	$schedule = [
		'id' => 'test-due',
		'expression' => '* * * * *',
		'job' => 'test:scheduled',
		'payload' => ['due' => true],
		'options' => [],
		'timezone' => 'UTC',
		'lastRun' => null,
		'nextRun' => time() - 60
	];

	$reflection = new ReflectionClass($scheduler);
	$scheduledProp = $reflection->getProperty('scheduled');
	$scheduledProp->setAccessible(true);
	$scheduledProp->setValue($scheduler, ['test-due' => $schedule]);

	$queuedIds = $scheduler->runDue();

	expect($queuedIds)->toHaveCount(1);

	$jobs = $manager->storage()->getByStatus('pending', 10);
	expect($jobs)->toHaveCount(1);
	expect($jobs[0]['type'])->toBe('test:scheduled');
	expect($jobs[0]['payload'])->toBe(['due' => true]);
});

test('scheduler prevents overlapping runs when configured', function () {
	$scheduler = Queues::scheduler();

	$scheduler->schedule('* * * * *', ScheduledTestJob::class, ['test' => 'overlap'], ['withoutOverlapping' => true]);

	$scheduled = array_values($scheduler->all())[0];

	expect($scheduled['options']['withoutOverlapping'])->toBe(true);

	$scheduler->unschedule($scheduled['id']);
});

test('scheduler handles different timezones', function () {
	$scheduler = Queues::scheduler();

	$scheduler->schedule('0 15 * * *', ScheduledTestJob::class, [], ['timezone' => 'America/New_York']);

	$scheduled = array_values($scheduler->all())[0];
	expect($scheduled['timezone'])->toBe('America/New_York');
	expect($scheduled['nextRun'])->toBeGreaterThan(time());
});

test('scheduler generates consistent IDs', function () {
	$scheduler = Queues::scheduler();

	$scheduler->schedule('0 * * * *', ScheduledTestJob::class);

	expect($scheduler->all())->toHaveCount(1);

	$scheduler->schedule('30 * * * *', ScheduledTestJob::class);
	expect($scheduler->all())->toHaveCount(2);
});

test('scheduler persists scheduled jobs', function () {
	$scheduler = Queues::scheduler();

	$scheduler->schedule('0 0 * * 0', ScheduledTestJob::class, ['persist' => 'test']);

	$newScheduler = Queues::scheduler();

	$scheduled = $newScheduler->all();
	expect($scheduled)->toHaveCount(1);
	expect(array_values($scheduled)[0]['payload'])->toBe(['persist' => 'test']);
});

test('scheduler calculates next run correctly', function () {
	$scheduler = Queues::scheduler();

	$scheduler->schedule('* * * * *', ScheduledTestJob::class);
	$scheduled = array_values($scheduler->all())[0];

	expect($scheduled['nextRun'])->not->toBeNull();

	$now = time();
	$currentMinute = $now - ($now % 60);
	$nextMinute = $currentMinute + 60;

	expect($scheduled['nextRun'])->toBeGreaterThanOrEqual($currentMinute);
	expect($scheduled['nextRun'])->toBeLessThanOrEqual($nextMinute);

	$scheduler->unschedule($scheduled['id']);

	$scheduler->schedule('0 * * * *', ScheduledTestJob::class);
	$scheduled = array_values($scheduler->all())[0];

	$nextRunDate = new DateTime('@' . $scheduled['nextRun']);
	expect($nextRunDate->format('i'))->toBe('00');
	expect($scheduled['nextRun'])->toBeGreaterThan(time());

	$scheduler->unschedule($scheduled['id']);
});

test('scheduler handles job queueing errors gracefully', function () {
	$scheduler = Queues::scheduler();

	$schedule = [
		'id' => 'test-error',
		'expression' => '* * * * *',
		'job' => 'non:existent',
		'payload' => [],
		'options' => [],
		'timezone' => 'UTC',
		'lastRun' => null,
		'nextRun' => time() - 60
	];

	$reflection = new ReflectionClass($scheduler);
	$scheduledProp = $reflection->getProperty('scheduled');
	$scheduledProp->setAccessible(true);
	$scheduledProp->setValue($scheduler, ['test-error' => $schedule]);

	$queued = $scheduler->runDue();
	expect($queued)->toBeEmpty();
});
