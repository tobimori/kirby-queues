# Kirby Queues Plugin - Design & Implementation Document

## 1. Executive Summary

**Plugin Name**: `tobimori/queues`
**Purpose**: A comprehensive background job queue system for Kirby CMS with scheduled task support and panel monitoring interface
**Target Audience**: Kirby developers who need to run background tasks, scheduled jobs, and long-running processes
**Key Features**:
- Background job processing with multiple queue support
- Cron-style scheduled tasks
- Laravel Horizon-inspired panel interface
- Unified worker for both queued and scheduled jobs
- Cache-based storage with support for Redis/File/Memory drivers

## 2. Architecture Overview

### 2.1 Core Components

```
┌─────────────────────────────────────────────────────────────┐
│                        Panel Interface                       │
│  ┌─────────┐ ┌─────────┐ ┌──────────┐ ┌──────────────────┐ │
│  │  Jobs   │ │ Running │ │Scheduled │ │     Metrics      │ │
│  └─────────┘ └─────────┘ └──────────┘ └──────────────────┘ │
└─────────────────────────────────────────────────────────────┘
                              │
                         API Routes
                              │
┌─────────────────────────────────────────────────────────────┐
│                      Queues Facade                          │
│  ┌─────────────┐ ┌──────────────┐ ┌───────────────────────┐│
│  │   register() │ │    push()    │ │     schedule()      ││
│  └─────────────┘ └──────────────┘ └───────────────────────┘│
└─────────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────────────────────────────────────┐
│                    Queue Manager                            │
│  ┌─────────────┐ ┌──────────────┐ ┌───────────────────────┐│
│  │   Storage   │ │   Scheduler  │ │       Worker         ││
│  └─────────────┘ └──────────────┘ └───────────────────────┘│
└─────────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────────────────────────────────────┐
│                   Cache Layer (Kirby)                       │
│           File / Redis / Memcached / APCu                   │
└─────────────────────────────────────────────────────────────┘
```

### 2.2 Directory Structure

```
site/plugins/kirby-queues/
├── index.php                    # Plugin registration
├── composer.json               # Composer configuration
├── README.md                   # Documentation
├── LICENSE.md                  # MIT License
│
├── classes/                    # Core classes
│   ├── Queues.php             # Main facade
│   ├── Manager.php            # Queue manager
│   ├── Job.php                # Abstract job base class
│   ├── JobResult.php          # Job result wrapper
│   ├── JobStatus.php          # Status enumeration
│   │
│   ├── Schedule/              # Scheduling system
│   │   ├── Scheduler.php      # Schedule manager
│   │   ├── ScheduledJob.php   # Scheduled job wrapper
│   │   └── CronExpression.php # Cron parser
│   │
│   ├── Storage/               # Storage implementations
│   │   ├── StorageInterface.php
│   │   └── CacheStorage.php   # Kirby cache adapter
│   │
│   ├── Worker/                # Worker implementation
│   │   ├── Worker.php         # Main worker class
│   │   └── WorkerStatus.php   # Worker state tracking
│   │
│   └── Panel/                 # Panel-specific classes
│       └── Views/
│           ├── QueuesView.php
│           ├── JobsView.php
│           ├── ScheduledView.php
│           ├── FailedView.php
│           └── MetricsView.php
│
├── config/                    # Configuration files
│   ├── options.php           # Default options
│   ├── commands/             # CLI commands
│   │   ├── work.php          # Main worker command
│   │   ├── status.php        # Status command
│   │   ├── retry.php         # Retry failed jobs
│   │   └── clear.php         # Clear old jobs
│   │
│   ├── api/                  # API routes
│   │   └── queues.php        # Queue API endpoints
│   │
│   └── areas/                # Panel areas
│       └── queues.php        # Queue panel area
│
├── src/                      # Frontend assets
│   └── panel/
│       └── components/       # Vue components (if needed)
│
├── translations/             # Language files
│   ├── en.php               # English
│   └── de.php               # German
│
└── helpers.php              # Helper functions
```

## 3. Detailed Component Design

### 3.1 Queues Facade

```php
namespace tobimori\Queues;

/**
 * Main entry point for the Queues system
 */
class Queues
{
    /**
     * Register job classes from other plugins
     */
    public static function register(string|array $jobs): void

    /**
     * Push a job to the queue
     */
    public static function push(string|Job $job, array $payload = []): string

    /**
     * Push a job with delay
     */
    public static function later(int $delay, string|Job $job, array $payload = []): string

    /**
     * Schedule a recurring job
     */
    public static function schedule(string $expression, string|Job $job, array $payload = []): void

    /**
     * Get queue manager instance
     */
    public static function manager(): Manager

    /**
     * Get scheduler instance
     */
    public static function scheduler(): Scheduler
}
```

### 3.2 Job Base Class

```php
namespace tobimori\Queues;

abstract class Job
{
    protected array $payload = [];
    protected array $options = [];
    protected int $attempts = 0;

    /**
     * Unique job type identifier
     */
    abstract public function type(): string;

    /**
     * Human-readable job name
     */
    abstract public function name(): string;

    /**
     * Execute the job
     */
    abstract public function handle(): void;

    /**
     * Handle job failure
     */
    public function failed(\Exception $exception): void
    {
        // Override in subclass if needed
    }

    /**
     * Determine if job should be retried
     */
    public function shouldRetry(): bool
    {
        return $this->attempts < $this->maxAttempts();
    }

    /**
     * Get maximum attempts
     */
    public function maxAttempts(): int
    {
        return $this->options['attempts'] ?? 3;
    }
}
```

### 3.3 Storage Interface

```php
namespace tobimori\Queues\Storage;

interface StorageInterface
{
    /**
     * Push job to queue
     */
    public function push(string $queue, array $job): void;

    /**
     * Pop next job from queue
     */
    public function pop(string $queue): ?array;

    /**
     * Get job by ID
     */
    public function get(string $id): ?array;

    /**
     * Update job
     */
    public function update(string $id, array $data): void;

    /**
     * Delete job
     */
    public function delete(string $id): void;

    /**
     * Get jobs by status
     */
    public function getByStatus(string $status, int $limit = 100): array;

    /**
     * Get queue statistics
     */
    public function stats(): array;
}
```

## 4. Implementation Plan

### Phase 1: Core Infrastructure (Week 1)

**Objectives**:
- Implement basic queue system
- Cache-based storage
- Simple job processing

**Tasks**:
1. Create plugin structure and registration
2. Implement Queues facade
3. Create Job base class
4. Implement CacheStorage
5. Basic Manager implementation
6. Simple CLI worker command

**Deliverables**:
- Working queue system
- Ability to push and process jobs
- Basic CLI interface

### Phase 2: Scheduling System (Week 2)

**Objectives**:
- Add cron-style scheduling
- Unified worker for queued and scheduled jobs

**Tasks**:
1. Implement CronExpression parser
2. Create Scheduler class
3. Integrate scheduler with worker
4. Add schedule registration API
5. Test various cron patterns

**Deliverables**:
- Scheduled job support
- Unified worker handling both job types
- Schedule listing command

### Phase 3: Panel Interface (Week 3)

**Objectives**:
- Create Horizon-style monitoring interface
- Real-time job tracking

**Tasks**:
1. Create panel area structure
2. Implement view classes
3. Create API endpoints
4. Design table layouts
5. Add job actions (retry, delete)
6. Create metrics dashboard

**Deliverables**:
- Full panel interface
- Job monitoring and management
- Metrics visualization

### Phase 4: Advanced Features (Week 4)

**Objectives**:
- Job batching
- Failed job handling
- Performance optimization

**Tasks**:
1. Implement job batching
2. Failed job retry logic
3. Add job chaining
4. Performance metrics collection
5. Memory optimization
6. Documentation

**Deliverables**:
- Advanced job features
- Complete documentation
- Performance benchmarks

## 5. API Design

### 5.1 Plugin Registration

```php
// In another plugin
use tobimori\Queues\Queues;

Kirby::plugin('vendor/plugin', [
    'hooks' => [
        'system.loadPlugins:after' => function () {
            // Register jobs
            Queues::register([
                MyJob::class,
                AnotherJob::class
            ]);

            // Schedule recurring jobs
            Queues::schedule('0 2 * * *', DailyReportJob::class);
            Queues::schedule('*/15 * * * *', HealthCheckJob::class);
        }
    ]
]);
```

### 5.2 Job Implementation

```php
namespace Vendor\Plugin\Jobs;

use tobimori\Queues\Job;

class ProcessImageJob extends Job
{
    public function type(): string
    {
        return 'vendor:process-image';
    }

    public function name(): string
    {
        return 'Process Image';
    }

    public function handle(): void
    {
        $file = kirby()->file($this->payload['fileId']);

        if (!$file) {
            throw new \Exception('File not found');
        }

        // Process image
        $this->progress(50);

        // More processing...
        $this->progress(100);
    }

    public function failed(\Exception $e): void
    {
        // Clean up temporary files
        // Send notification
    }
}
```

### 5.3 Usage Examples

```php
use tobimori\Queues\Queues;

// Simple job dispatch
Queues::push('vendor:process-image', ['fileId' => $file->id()]);

// Delayed job
Queues::later(300, 'vendor:send-email', ['to' => 'user@example.com']);

// Using job instance
$job = new ProcessImageJob();
$job->payload(['fileId' => $file->id()])
    ->onQueue('images')
    ->delay(60);
Queues::push($job);

// Job chaining
Queues::push('vendor:import-data')
    ->then('vendor:process-data')
    ->then('vendor:send-report');
```

## 6. Configuration

### 6.1 Default Options

```php
// config/options.php
return [
    // Queue defaults
    'default' => 'default',
    'connection' => [
        'driver' => 'cache',
        'cache' => 'queues'
    ],

    // Worker configuration
    'worker' => [
        'timeout' => 60,      // seconds
        'memory' => 128,      // MB
        'sleep' => 5,         // seconds between checks
        'tries' => 3,         // max attempts
        'backoff' => 60,      // seconds between retries
        'maxJobs' => 1000     // jobs before restart
    ],

    // Job retention
    'retention' => [
        'completed' => 24,    // hours
        'failed' => 168       // 7 days
    ],

    // Scheduling
    'schedule' => [
        'timezone' => 'UTC',
        'overlap' => false    // prevent overlapping
    ]
];
```

### 6.2 Cache Configuration

```php
// Automatic cache registration
'caches' => [
    'queues' => function () {
        $driver = option('tobimori.queues.connection.driver', 'file');

        return match ($driver) {
            'redis' => [
                'type' => 'redis',
                'host' => option('tobimori.queues.redis.host', 'localhost'),
                'port' => option('tobimori.queues.redis.port', 6379)
            ],
            default => [
                'type' => 'file',
                'prefix' => 'queues'
            ]
        };
    }
]
```

## 7. CLI Commands

### 7.1 Worker Command

```bash
# Process jobs continuously
php kirby queues:work

# Process with options
php kirby queues:work \
  --queue=images \
  --timeout=3600 \
  --memory=256 \
  --sleep=10

# Process single job
php kirby queues:work --once
```

### 7.2 Management Commands

```bash
# Check status
php kirby queues:status

# Retry failed jobs
php kirby queues:retry --all
php kirby queues:retry <job-id>

# Clear old jobs
php kirby queues:clear --hours=24

# List scheduled jobs
php kirby queues:schedule
```

## 8. Panel Interface

### 8.1 Routes

- `/panel/queues` - Redirect to jobs
- `/panel/queues/jobs` - Pending jobs table
- `/panel/queues/running` - Currently running jobs
- `/panel/queues/scheduled` - Scheduled jobs
- `/panel/queues/failed` - Failed jobs with retry options
- `/panel/queues/metrics` - Performance metrics

### 8.2 Features

1. **Job Management**
   - View job details
   - Run jobs immediately
   - Delete pending jobs
   - Retry failed jobs

2. **Real-time Monitoring**
   - Auto-refresh every 5 seconds
   - Progress indicators
   - Status badges

3. **Metrics Dashboard**
   - Jobs per hour
   - Average wait time
   - Average process time
   - Success/failure rates
   - Queue depths

## 9. Testing Strategy

### 9.1 Unit Tests

```php
// tests/QueueTest.php
class QueueTest extends TestCase
{
    public function testJobRegistration()
    {
        Queues::register(TestJob::class);
        $this->assertContains(TestJob::class, Queues::jobs());
    }

    public function testJobDispatch()
    {
        $jobId = Queues::push(TestJob::class, ['data' => 'test']);
        $this->assertNotEmpty($jobId);
    }
}
```

### 9.2 Integration Tests

- Test with different cache drivers
- Test scheduled job execution
- Test worker memory management
- Test panel interface endpoints

## 10. Performance Considerations

### 10.1 Optimization Strategies

1. **Batch Processing**: Process multiple small jobs together
2. **Memory Management**: Reset Kirby instance after each job
3. **Cache Efficiency**: Use Redis for high-throughput scenarios
4. **Query Optimization**: Limit job queries to necessary fields

### 10.2 Scaling Recommendations

- **Small Sites**: File cache with single worker
- **Medium Sites**: Redis cache with 2-3 workers
- **Large Sites**: Redis cluster with multiple workers per queue

## 11. Security Considerations

1. **Job Authorization**: Validate job permissions
2. **Payload Validation**: Sanitize job payloads
3. **Panel Access**: Restrict to admin users
4. **Rate Limiting**: Prevent job spam
5. **Secure Storage**: Encrypt sensitive job data

## 12. Documentation Plan

### 12.1 User Documentation

1. **Getting Started Guide**
2. **Job Creation Tutorial**
3. **Scheduling Guide**
4. **Panel Interface Guide**
5. **Troubleshooting**

### 12.2 Developer Documentation

1. **API Reference**
2. **Custom Storage Drivers**
3. **Event Hooks**
4. **Performance Tuning**
5. **Contributing Guide**
