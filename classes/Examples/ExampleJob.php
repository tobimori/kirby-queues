<?php

namespace tobimori\Queues\Examples;

use tobimori\Queues\Job;

/**
 * Example job for demonstrating queue functionality
 */
class ExampleJob extends Job
{
    /**
     * Unique job type identifier
     */
    public function type(): string
    {
        return 'queues:example-job';
    }
    
    /**
     * Human-readable job name
     */
    public function name(): string
    {
        return 'Example Queue Job';
    }
    
    /**
     * Execute the job
     */
    public function handle(): void
    {
        $message = $this->payload['message'] ?? 'Processing...';
        $duration = $this->payload['duration'] ?? 2;
        
        // Log the start
        $this->logInfo("Starting job with message: {$message}", [
            'duration' => $duration
        ]);
        
        // Simulate work with progress updates
        for ($i = 1; $i <= $duration; $i++) {
            $progress = ($i / $duration) * 100;
            $this->progress($progress, "Step {$i} of {$duration}");
            
            // Log each step
            $this->logDebug("Processing step {$i}/{$duration}", [
                'progress' => $progress
            ]);
            
            sleep(1);
        }
        
        // Log completion
        $this->logInfo("Job completed successfully");
    }
    
    /**
     * Handle a failed job
     */
    public function failed(\Exception $exception): void
    {
        error_log("[Example Job {$this->id()}] Failed: " . $exception->getMessage());
    }
}