---
title: Creating Jobs
---

Jobs are the building blocks of your background processing system. Each job is a PHP class that encapsulates a specific task, from sending emails to processing uploaded files. This guide will walk you through creating your first job and explore advanced features for building robust background tasks.

## Your First Job

Let's create a simple job that demonstrates the basic structure:

```php
<?php

namespace App\Jobs;

use tobimori\Queues\Job;

class ProcessDataJob extends Job
{
    public function handle(): void
    {
        // Get data from payload
        $itemId = $this->payload()['itemId'];
        $action = $this->payload()['action'];
        
        $this->log('info', 'Starting processing', [
            'itemId' => $itemId,
            'action' => $action
        ]);
        
        // Simulate some work
        sleep(2);
        
        // Do your actual work here
        // $this->processItem($itemId, $action);
        
        $this->log('info', 'Processing completed');
    }
    
    public function type(): string
    {
        return 'process-data';
    }
    
    public function name(): string
    {
        return 'Process Data';
    }
}
```

## Registering Jobs

Before you can use a job, you need to register it with the queue system. Add this to your plugin's `index.php` or in a `system.loadPlugins:after` hook:

```php
use tobimori\Queues\Queues;
use App\Jobs\ProcessDataJob;

Queues::register(ProcessDataJob::class);
```

You can also register multiple jobs at once:

```php
Queues::register([
    ProcessDataJob::class,
    SendNewsletter::class,
    GenerateReport::class
]);
```

## Dispatching Jobs

Once registered, you can push jobs to the queue:

```php
// Simple dispatch
queue(ProcessDataJob::class, [
    'itemId' => 'abc123',
    'action' => 'process'
]);

// Dispatch with delay (process in 5 minutes)
queueLater(300, ProcessDataJob::class, [
    'itemId' => 'abc123',
    'action' => 'process'
]);

// Dispatch to specific queue
queue(ProcessDataJob::class, ['itemId' => 'abc123'], 'high');
```

## Job Lifecycle

### Required Methods

Every job must implement three methods:

```php
public function handle(): void
{
    // Your job logic here
}

public function type(): string
{
    // Unique identifier for this job type
    return 'my-job-type';
}

public function name(): string
{
    // Human-readable name for the Panel
    return 'My Job';
}
```

### Optional Methods

Override these methods to customize job behavior:

```php
public function failed(\Exception $exception): void
{
    // Called when job fails after all retries
    // Send notification, log error, cleanup, etc.
    kirby()->email([
        'to' => 'admin@example.com',
        'subject' => 'Job failed: ' . $this->name(),
        'body' => $exception->getMessage()
    ]);
}

public function timeout(): int
{
    // Override default timeout (in seconds)
    return 300; // 5 minutes
}

public function maxAttempts(): int
{
    // Override default retry attempts
    return 5;
}

public function retryBackoff(): int
{
    // Custom retry delay calculation
    return $this->attempts() * 60; // Linear backoff
}
```

## Working with Payload

The payload is the data passed to your job. Access it using the `payload()` method:

```php
public function handle(): void
{
    $userId = $this->payload()['userId'];
    $action = $this->payload()['action'];
    $options = $this->payload()['options'] ?? [];
    
    // Always validate your payload
    if (!$userId) {
        throw new \InvalidArgumentException('User ID is required');
    }
}
```


## Logging

Jobs can log messages at different levels. Available log levels are:
- `info` - General information messages
- `warning` - Warning messages that don't stop execution
- `error` - Error messages for failures
- `debug` - Detailed debugging information

```php
public function handle(): void
{
    $this->log('info', 'Starting process');
    $this->log('debug', 'Payload received', $this->payload());
    
    try {
        // Do something risky
        $result = $this->performAction();
        $this->log('info', 'Action completed', ['result' => $result]);
    } catch (\Exception $e) {
        $this->log('error', 'Action failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e; // Re-throw to mark job as failed
    }
}
```

## Error Handling

Jobs should handle errors gracefully and provide meaningful error messages:

```php
public function handle(): void
{
    $pageId = $this->payload()['pageId'];
    
    $page = kirby()->page($pageId);
    if (!$page) {
        throw new \RuntimeException("Page not found: {$pageId}");
    }
    
    try {
        $this->processPage($page);
    } catch (\Exception $e) {
        // Log the error with context
        $this->log('error', 'Failed to process page', [
            'pageId' => $pageId,
            'error' => $e->getMessage()
        ]);
        
        // Decide whether to retry
        if ($e instanceof \RuntimeException) {
            throw $e; // Will retry
        }
        
        // Don't retry for logic errors
        $this->failed($e);
        return;
    }
}
```


## Best Practices

### Keep Jobs Focused

Each job should do one thing well. Instead of a massive `ProcessOrder` job, break it down:

```php
// Good: Focused jobs
queue(ValidateOrderData::class, ['orderId' => $id]);
queue(ChargePayment::class, ['orderId' => $id]);
queue(SendOrderConfirmation::class, ['orderId' => $id]);
queue(NotifyWarehouse::class, ['orderId' => $id]);

// Bad: Monolithic job
queue(ProcessEverythingAboutOrder::class, ['orderId' => $id]);
```

### Make Jobs Idempotent

Jobs should be safe to run multiple times with the same payload:

```php
public function handle(): void
{
    $importId = $this->payload()['importId'];
    
    // Check if already processed
    if (kirby()->cache('imports')->get($importId)) {
        $this->log('info', 'Import already processed, skipping');
        return;
    }
    
    // Process import...
    
    // Mark as processed
    kirby()->cache('imports')->set($importId, true, 60 * 24 * 7);
}
```

### Handle State Changes Carefully

When modifying content, always verify the current state:

```php
public function handle(): void
{
    $page = kirby()->page($this->payload()['pageId']);
    
    // Don't assume the page still exists
    if (!$page) {
        $this->log('warning', 'Page no longer exists');
        return;
    }
    
    // Don't assume status hasn't changed
    if ($page->status() !== 'draft') {
        $this->log('info', 'Page already published');
        return;
    }
    
    kirby()->impersonate('kirby', fn() => $page->changeStatus('listed'));
}
```

## Next Steps

Now that you understand how to create powerful background jobs, learn how to [schedule recurring tasks](3_scheduling.md) to automate routine operations in your Kirby application.