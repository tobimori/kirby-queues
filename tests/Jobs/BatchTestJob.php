<?php

namespace tobimori\Queues\Tests\Jobs;

use tobimori\Queues\BatchJob;

class BatchTestJob extends BatchJob
{
	protected int $batchWindow = 5;

	public static array $receivedPayloads = [];

	public function handle(): void
	{
		self::$receivedPayloads = $this->payload();
	}

	public function type(): string
	{
		return 'batch-test-job';
	}

	public function name(): string
	{
		return 'Batch Test Job';
	}

	public static function reset(): void
	{
		self::$receivedPayloads = [];
	}
}
