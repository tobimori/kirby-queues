<?php

use tobimori\Queues\Schedule\CronExpression;

test('cron expression validates correctly', function () {
	// Valid expressions
	expect(CronExpression::isValid('* * * * *'))->toBeTrue();
	expect(CronExpression::isValid('0 * * * *'))->toBeTrue();
	expect(CronExpression::isValid('*/5 * * * *'))->toBeTrue();
	expect(CronExpression::isValid('0 9-17 * * 1-5'))->toBeTrue();
	expect(CronExpression::isValid('0,30 * * * *'))->toBeTrue();
	expect(CronExpression::isValid('0 0 1,15 * *'))->toBeTrue();

	// Invalid expressions
	expect(CronExpression::isValid('* * * *'))->toBeFalse(); // Too few parts
	expect(CronExpression::isValid('* * * * * *'))->toBeFalse(); // Too many parts
	expect(CronExpression::isValid('60 * * * *'))->toBeFalse(); // Invalid minute
	expect(CronExpression::isValid('* 24 * * *'))->toBeFalse(); // Invalid hour
	expect(CronExpression::isValid('* * 32 * *'))->toBeFalse(); // Invalid day
	expect(CronExpression::isValid('* * * 13 *'))->toBeFalse(); // Invalid month
	expect(CronExpression::isValid('* * * * 8'))->toBeFalse(); // Invalid day of week
	expect(CronExpression::isValid('*/0 * * * *'))->toBeFalse(); // Invalid step
	expect(CronExpression::isValid('invalid'))->toBeFalse();
});

test('cron expression parses wildcards', function () {
	$cron = new CronExpression('* * * * *');

	// Should match any time
	$date = new DateTime('2024-01-15 10:30:00');
	expect($cron->isDue($date))->toBeTrue();

	$date = new DateTime('2024-12-31 23:59:00');
	expect($cron->isDue($date))->toBeTrue();
});

test('cron expression parses specific values', function () {
	$cron = new CronExpression('30 14 15 6 2');

	// Should match June 15th, 2:30 PM on a Tuesday
	$date = new DateTime('2021-06-15 14:30:00'); // This was a Tuesday
	expect($cron->isDue($date))->toBeTrue();

	// Should not match different minute
	$date = new DateTime('2021-06-15 14:31:00');
	expect($cron->isDue($date))->toBeFalse();

	// Should not match different hour
	$date = new DateTime('2021-06-15 15:30:00');
	expect($cron->isDue($date))->toBeFalse();
});

test('cron expression parses ranges', function () {
	$cron = new CronExpression('0 9-17 * * 1-5');

	// Should match weekdays 9 AM - 5 PM
	$date = new DateTime('2024-01-15 09:00:00'); // Monday
	expect($cron->isDue($date))->toBeTrue();

	$date = new DateTime('2024-01-15 17:00:00'); // Monday
	expect($cron->isDue($date))->toBeTrue();

	$date = new DateTime('2024-01-15 08:00:00'); // Monday, too early
	expect($cron->isDue($date))->toBeFalse();

	$date = new DateTime('2024-01-15 18:00:00'); // Monday, too late
	expect($cron->isDue($date))->toBeFalse();

	$date = new DateTime('2024-01-14 12:00:00'); // Sunday
	expect($cron->isDue($date))->toBeFalse();
});

test('cron expression parses lists', function () {
	$cron = new CronExpression('0,15,30,45 * * * *');

	// Should match at 0, 15, 30, 45 minutes
	$date = new DateTime('2024-01-15 10:00:00');
	expect($cron->isDue($date))->toBeTrue();

	$date = new DateTime('2024-01-15 10:15:00');
	expect($cron->isDue($date))->toBeTrue();

	$date = new DateTime('2024-01-15 10:30:00');
	expect($cron->isDue($date))->toBeTrue();

	$date = new DateTime('2024-01-15 10:45:00');
	expect($cron->isDue($date))->toBeTrue();

	$date = new DateTime('2024-01-15 10:10:00');
	expect($cron->isDue($date))->toBeFalse();
});

test('cron expression parses steps', function () {
	$cron = new CronExpression('*/15 * * * *');

	// Should match every 15 minutes
	$date = new DateTime('2024-01-15 10:00:00');
	expect($cron->isDue($date))->toBeTrue();

	$date = new DateTime('2024-01-15 10:15:00');
	expect($cron->isDue($date))->toBeTrue();

	$date = new DateTime('2024-01-15 10:30:00');
	expect($cron->isDue($date))->toBeTrue();

	$date = new DateTime('2024-01-15 10:45:00');
	expect($cron->isDue($date))->toBeTrue();

	$date = new DateTime('2024-01-15 10:10:00');
	expect($cron->isDue($date))->toBeFalse();
});

test('cron expression parses range with steps', function () {
	$cron = new CronExpression('0 10-14/2 * * *');

	// Should match at 10, 12, 14
	$date = new DateTime('2024-01-15 10:00:00');
	expect($cron->isDue($date))->toBeTrue();

	$date = new DateTime('2024-01-15 12:00:00');
	expect($cron->isDue($date))->toBeTrue();

	$date = new DateTime('2024-01-15 14:00:00');
	expect($cron->isDue($date))->toBeTrue();

	$date = new DateTime('2024-01-15 11:00:00');
	expect($cron->isDue($date))->toBeFalse();

	$date = new DateTime('2024-01-15 13:00:00');
	expect($cron->isDue($date))->toBeFalse();
});

test('cron expression handles Sunday correctly', function () {
	$cron1 = new CronExpression('0 0 * * 0'); // Sunday with 0
	$cron2 = new CronExpression('0 0 * * 7'); // Sunday with 7

	$sunday = new DateTime('2024-01-14 00:00:00'); // A Sunday
	expect($cron1->isDue($sunday))->toBeTrue();
	expect($cron2->isDue($sunday))->toBeTrue();

	$monday = new DateTime('2024-01-15 00:00:00'); // A Monday
	expect($cron1->isDue($monday))->toBeFalse();
	expect($cron2->isDue($monday))->toBeFalse();
});

test('cron expression calculates next run date', function () {
	$cron = new CronExpression('0 * * * *'); // Every hour

	$from = new DateTime('2024-01-15 10:30:00');
	$next = $cron->getNextRunDate($from);

	// Should be 11:00:00
	expect($next->format('Y-m-d H:i:s'))->toBe('2024-01-15 11:00:00');
});

test('cron expression calculates next run date for complex expressions', function () {
	// Every weekday at 9 AM
	$cron = new CronExpression('0 9 * * 1-5');

	// Use a more recent date to avoid null returns
	$from = new DateTime('2025-01-10 15:00:00'); // Friday
	$next = $cron->getNextRunDate($from);

	// Should be Monday 9 AM
	expect($next)->not->toBeNull();
	if ($next !== null) {
		// Next weekday would be Monday
		expect($next->format('N'))->toBe('1'); // Monday
		expect($next->format('H:i'))->toBe('09:00');
	}
});

test('cron expression calculates next run date with specific day of month', function () {
	// First day of month at midnight
	$cron = new CronExpression('0 0 1 * *');

	$from = new DateTime('2025-01-15 10:00:00');
	$next = $cron->getNextRunDate($from);

	// Should be first of next month
	expect($next)->not->toBeNull();
	if ($next !== null) {
		expect($next->format('d'))->toBe('01');
		expect($next->format('H:i'))->toBe('00:00');
		// Should be in the future
		expect($next->getTimestamp())->toBeGreaterThan($from->getTimestamp());
	}
});

test('cron expression handles edge cases', function () {
	// February 30th doesn't exist, should skip
	$cron = new CronExpression('0 0 30 2 *');

	$from = new DateTime('2024-01-01');
	$next = $cron->getNextRunDate($from);

	// Should return null or skip to valid date
	expect($next)->toBeNull();
});

test('cron expression validates field boundaries', function () {
	// Test boundary validation in constructor
	expect(fn () => new CronExpression('60 * * * *'))
		->toThrow(InvalidArgumentException::class, 'Value 60 out of bounds');

	expect(fn () => new CronExpression('* 24 * * *'))
		->toThrow(InvalidArgumentException::class, 'Value 24 out of bounds');

	expect(fn () => new CronExpression('* * 0 * *'))
		->toThrow(InvalidArgumentException::class, 'Value 0 out of bounds');

	expect(fn () => new CronExpression('* * 32 * *'))
		->toThrow(InvalidArgumentException::class, 'Value 32 out of bounds');

	expect(fn () => new CronExpression('* * * 0 *'))
		->toThrow(InvalidArgumentException::class, 'Value 0 out of bounds');

	expect(fn () => new CronExpression('* * * 13 *'))
		->toThrow(InvalidArgumentException::class, 'Value 13 out of bounds');
});

test('cron expression handles combined patterns', function () {
	// Complex expression: At 0 and 30 minutes past the hour, between 9-17, on weekdays
	$cron = new CronExpression('0,30 9-17 * * 1-5');

	// Monday 9:30 AM
	$date = new DateTime('2024-01-15 09:30:00');
	expect($cron->isDue($date))->toBeTrue();

	// Monday 9:15 AM (wrong minute)
	$date = new DateTime('2024-01-15 09:15:00');
	expect($cron->isDue($date))->toBeFalse();

	// Saturday 9:30 AM (weekend)
	$date = new DateTime('2024-01-13 09:30:00');
	expect($cron->isDue($date))->toBeFalse();

	// Monday 18:30 (after hours)
	$date = new DateTime('2024-01-15 18:30:00');
	expect($cron->isDue($date))->toBeFalse();
});
