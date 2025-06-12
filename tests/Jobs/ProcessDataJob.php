<?php

namespace tobimori\Queues\Tests\Jobs;

use tobimori\Queues\Job;

class ProcessDataJob extends Job
{
    public static $processed = [];

    public function handle(): void
    {
        $data = $this->payload()['data'] ?? 'test';
        self::$processed[] = $data;
        
        $this->log('info', 'Processing data: ' . $data);
    }

    public function type(): string
    {
        return 'process-data';
    }

    public function name(): string
    {
        return 'Process Data';
    }
    
    public static function reset(): void
    {
        self::$processed = [];
    }
}