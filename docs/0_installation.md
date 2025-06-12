---
title: Installation
---

Queues is a simple but sophisticated queueing API for Kirby CMS inspired by Laravel's elegant queueing system. It provides plugin developers with a common foundation for implementing background tasks, eliminating the need for each plugin to reinvent the wheel with its own queueing implementation.

Whether you're sending transactional emails, processing large image galleries, generating complex reports, or running time-intensive calculations, Queues gracefully handles these operations in the background, keeping your application responsive and your users happy.

## Requirements

Before diving into the world of background processing, make sure your environment meets these requirements:

- Kirby 5.0 or later
- PHP 8.3 or PHP 8.4
- Composer for dependency management
- Kirby CLI for running queue commands

## Installation

Getting started with Queues is straightforward. Simply install the plugin alongside the Kirby CLI using Composer:

```bash
composer require tobimori/kirby-queues getkirby/cli
```

## Getting Started

Let's walk through a simple example to see how it all comes together.
First, you'll create a job class that encapsulates the work you want to perform in the background:

```php
// Create a job class that extends the base Job
class SendWelcomeEmail extends \tobimori\Queues\Job
{
    public function handle(): void
    {
        // Extract the email address from the job's payload
        $email = $this->payload()['email'];
        $userName = $this->payload()['name'] ?? 'there';

        // Use Kirby's built-in email functionality
        kirby()->email([
            'to' => $email,
            'subject' => 'Welcome to our community!',
            'template' => 'welcome',
            'data' => [
                'name' => $userName,
                'activationLink' => url('activate/' . $this->payload()['token'])
            ]
        ]);

        // Log successful completion
        $this->log('info', "Welcome email sent to {$email}");
    }

    public function type(): string
    {
        return 'send-welcome-email';
    }

    public function name(): string
    {
        return 'Send Welcome Email';
    }
}

// Push the job to the queue with its payload
queue(SendWelcomeEmail::class, [
    'email' => 'user@example.com',
    'name' => 'Jane',
    'token' => 'abc123'
]);

// Need to delay the email? No problem!
queueLater(300, SendWelcomeEmail::class, [
    'email' => 'user@example.com',
    'name' => 'John',
    'token' => 'xyz789'
]);
```

To actually process these queued jobs, you'll run the worker command. During development, you can run this in a terminal window:

```bash
kirby queues:work
```

The worker will continuously poll for new jobs, process them, and handle any failures gracefully. In production, you'll want to keep this worker running permanently using a process manager like supervisord or systemd (more on that in the [workers documentation](1_workers.md)).

## Configuration

While Queues works beautifully out of the box, you can fine-tune its behavior to match your application's specific needs. All configuration happens in your `site/config/config.php` file under the `tobimori.queues` namespace.

### Queue Settings

| Option | Default | Accepts | Description |
| --- | --- | --- | --- |
| tobimori.queues.queues | `['default', 'high', 'low']` | `array` | Available queue names. You can add custom queues like 'emails', 'exports', or 'notifications' |
| tobimori.queues.default | `'default'` | `string` | The default queue used when you don't specify one explicitly |
| tobimori.queues.connection.cache | `'tobimori.queues'` | `string` | The cache store name used for queue storage |

### Worker Settings

These settings control how the queue worker processes jobs. Finding the right balance here ensures optimal performance without overwhelming your server:

| Option | Default | Accepts | Description |
| --- | --- | --- | --- |
| tobimori.queues.worker.timeout | `60` | `int` | Maximum seconds a job can run before being terminated |
| tobimori.queues.worker.memory | `128` | `int` | Memory limit in MB for the worker process |
| tobimori.queues.worker.sleep | `5` | `int` | Seconds to wait between checking for new jobs when the queue is empty |
| tobimori.queues.worker.tries | `3` | `int` | How many times to retry a job before marking it as failed |
| tobimori.queues.worker.backoff | `60` | `int` | Base seconds between retries (uses exponential backoff) |
| tobimori.queues.worker.maxJobs | `1000` | `int` | Number of jobs to process before restarting the worker (prevents memory leaks) |

### Job Retention

Control how long completed and failed jobs are kept in storage. This helps manage storage space while keeping useful debugging information:

| Option | Default | Accepts | Description |
| --- | --- | --- | --- |
| tobimori.queues.retention.completed | `24` | `int` | Hours to keep successfully completed jobs |
| tobimori.queues.retention.failed | `168` | `int` | Hours to keep failed jobs (default is 7 days for debugging) |

### Scheduling

Configure how scheduled jobs behave when using the built-in scheduler:

| Option | Default | Accepts | Description |
| --- | --- | --- | --- |
| tobimori.queues.schedule.timezone | `'UTC'` | `string` | Timezone for evaluating cron expressions |
| tobimori.queues.schedule.overlap | `false` | `bool` | Whether to allow the same scheduled job to run multiple times concurrently |

## Cache Drivers

Queues leverages Kirby's Cache API for storage, which means you can choose from various storage backends. While the default file-based cache works well for development, production environments will benefit from using Redis or SQLite.

For available cache drivers and configuration options, check out:
- [Kirby cache driver plugins](https://plugins.getkirby.com/types/cache-driver)
- [Cache configuration documentation](https://getkirby.com/docs/reference/system/options/cache)

## Panel Integration

Queues comes with a beautifully designed Panel area that gives you complete visibility into your background jobs. This isn't just a simple list – it's a comprehensive dashboard for monitoring, debugging, and managing your entire queue system.

To enable the Panel integration, you'll need to explicitly register the area in your `config.php`:

```php
return [
    'panel' => [
        'areas' => [
            'queues' // Add the queues area
        ]
    ]
];
```

Once enabled, you'll find the Queues area in your Panel's main menu. It's particularly valuable during development and debugging, as it gives you immediate insight into what's happening with your background jobs without needing to dig through logs or database entries.

## Next Steps

Now that you have Queues installed and configured, you're ready to start building powerful background processing into your Kirby applications. Here's where to go next:

- **[Set up workers](1_workers.md)** – Learn how to run queue workers in production using supervisord or systemd for maximum reliability
- **[Create your first job](2_creating-jobs.md)** – Dive deep into creating custom job classes, handling failures, and best practices
- **[Schedule recurring jobs](3_scheduling.md)** – Automate tasks with cron-like scheduling built right into the queue system
