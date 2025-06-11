<?php

namespace tobimori\Queues\Examples;

use tobimori\Queues\Job;

/**
 * Example failing job for demonstrating retry functionality
 */
class FailingExampleJob extends Job
{
    /**
     * Unique job type identifier
     */
    public function type(): string
    {
        return 'queues:failing-example';
    }
    
    /**
     * Human-readable job name
     */
    public function name(): string
    {
        return 'Failing Example Job';
    }
    
    /**
     * Execute the job (designed to fail)
     */
    public function handle(): void
    {
        $this->logInfo("Starting failing example job");
        
        $this->progress(50, "About to fail...");
        $this->logWarning("Job is about to fail intentionally");
        
        sleep(1);
        
        throw new \Exception("This job is designed to fail for demonstration purposes");
    }
    
    /**
     * Get maximum attempts
     */
    public function maxAttempts(): int
    {
        return 2;
    }
}