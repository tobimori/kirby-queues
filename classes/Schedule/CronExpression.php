<?php

namespace tobimori\Queues\Schedule;

/**
 * Simple cron expression parser
 *
 * Supports standard cron format:
 * - minute (0-59)
 * - hour (0-23)
 * - day of month (1-31)
 * - month (1-12)
 * - day of week (0-7, where 0 and 7 are Sunday)
 *
 * Special characters:
 * - * (any value)
 * - , (value list separator)
 * - - (range of values)
 * - / (step values)
 */
class CronExpression
{
	/**
	 * @var array Cron field boundaries
	 */
	protected static array $boundaries = [
		0 => ['min' => 0, 'max' => 59], // minute
		1 => ['min' => 0, 'max' => 23], // hour
		2 => ['min' => 1, 'max' => 31], // day of month
		3 => ['min' => 1, 'max' => 12], // month
		4 => ['min' => 0, 'max' => 7],  // day of week
	];

	/**
	 * @var array Parsed expression parts
	 */
	protected array $parts;

	/**
	 * Constructor
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct(protected string $expression)
	{
		$this->parts = $this->parse($expression);
	}

	/**
	 * Check if a cron expression is valid
	 */
	public static function isValid(string $expression): bool
	{
		try {
			new self($expression);
			return true;
		} catch (\InvalidArgumentException $e) {
			return false;
		}
	}

	/**
	 * Get the next run date
	 */
	public function getNextRunDate(\DateTime $from): ?\DateTime
	{
		$date = clone $from;
		$date->setTime($date->format('H'), $date->format('i'), 0);

		// try for up to 4 years to find next run date
		$maxAttempts = 366 * 4;
		$attempts = 0;

		while ($attempts < $maxAttempts) {
			// add one minute for next check
			if ($attempts > 0 || $this->isDue($date) === false) {
				$date->modify('+1 minute');
			}

			if ($this->isDue($date)) {
				return $date;
			}

			$attempts++;
		}

		return null;
	}

	/**
	 * Check if the cron is due at a specific time
	 */
	public function isDue(\DateTime $date): bool
	{
		return $this->matchesField(0, (int) $date->format('i'))     // minute
			&& $this->matchesField(1, (int) $date->format('H'))     // hour
			&& $this->matchesField(2, (int) $date->format('d'))     // day of month
			&& $this->matchesField(3, (int) $date->format('n'))     // month
			&& $this->matchesField(4, (int) $date->format('w'));    // day of week
	}

	/**
	 * Parse cron expression
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function parse(string $expression): array
	{
		$parts = preg_split('/\s+/', trim($expression));

		if (count($parts) !== 5) {
			throw new \InvalidArgumentException('Cron expression must have exactly 5 parts');
		}

		$parsed = [];

		foreach ($parts as $position => $part) {
			$parsed[$position] = $this->parsePart($part, $position);
		}

		return $parsed;
	}

	/**
	 * Parse a single cron part
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function parsePart(string $part, int $position): array
	{
		$bounds = self::$boundaries[$position];

		// handle wildcard
		if ($part === '*') {
			return ['*'];
		}

		$values = [];

		// handle lists (e.g., "1,3,5")
		foreach (explode(',', $part) as $listPart) {
			// handle steps (e.g., "*/5" or "10-20/2")
			if (str_contains($listPart, '/')) {
				[$range, $step] = explode('/', $listPart, 2);
				
				if (!is_numeric($step) || $step < 1) {
					throw new \InvalidArgumentException("Invalid step value: {$step}");
				}

				$step = (int) $step;

				// determine range
				if ($range === '*') {
					$start = $bounds['min'];
					$end = $bounds['max'];
				} elseif (str_contains($range, '-')) {
					[$start, $end] = explode('-', $range, 2);
					$start = (int) $start;
					$end = (int) $end;
				} else {
					throw new \InvalidArgumentException("Invalid range for step: {$range}");
				}

				// generate values with step
				for ($i = $start; $i <= $end; $i += $step) {
					if ($i >= $bounds['min'] && $i <= $bounds['max']) {
						$values[] = $i;
					}
				}
			}
			// handle ranges (e.g., "10-20")
			elseif (str_contains($listPart, '-')) {
				[$start, $end] = explode('-', $listPart, 2);
				$start = (int) $start;
				$end = (int) $end;

				if ($start > $end) {
					throw new \InvalidArgumentException("Invalid range: {$listPart}");
				}

				for ($i = $start; $i <= $end; $i++) {
					if ($i >= $bounds['min'] && $i <= $bounds['max']) {
						$values[] = $i;
					}
				}
			}
			// handle single values
			else {
				$value = (int) $listPart;

				if ($value < $bounds['min'] || $value > $bounds['max']) {
					throw new \InvalidArgumentException(
						"Value {$value} out of bounds [{$bounds['min']}-{$bounds['max']}] for position {$position}"
					);
				}

				$values[] = $value;
			}
		}

		// handle day of week special case (0 and 7 both mean Sunday)
		if ($position === 4) {
			$values = array_map(fn($v) => $v === 7 ? 0 : $v, $values);
		}

		return array_unique($values);
	}

	/**
	 * Check if a field matches a value
	 */
	protected function matchesField(int $position, int $value): bool
	{
		$fieldValues = $this->parts[$position];

		// wildcard matches everything
		if ($fieldValues === ['*']) {
			return true;
		}

		// handle day of week special case
		if ($position === 4 && $value === 0) {
			return in_array(0, $fieldValues) || in_array(7, $fieldValues);
		}

		return in_array($value, $fieldValues);
	}
}