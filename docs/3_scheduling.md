---
title: Scheduling
---

Scheduling allows you to run jobs automatically at specific times or intervals, similar to cron jobs but managed entirely within your Kirby application. This is perfect for routine tasks like sending daily reports, cleaning up old data, or synchronizing content.

## How Scheduling Works

The scheduler runs as part of your queue worker. When you start a worker with `kirby queues:work`, it automatically checks for due scheduled jobs every minute and pushes them to the queue for processing.

## Creating Scheduled Jobs

Scheduled jobs are regular job classes - there's nothing special about them. You create them exactly as shown in the [Creating Jobs](2_creating-jobs.md) documentation.

## Scheduling Your Job

Schedule jobs in your plugin's `index.php` or in a `system.loadPlugins:after` hook:

```php
use tobimori\Queues\Queues;
use App\Jobs\ProcessDataJob;

// Register the job
Queues::register(ProcessDataJob::class);

// Schedule it to run daily at 2 AM
Queues::schedule('0 2 * * *', ProcessDataJob::class);

// You can also pass payload data
Queues::schedule('0 2 * * *', ProcessDataJob::class, [
    'action' => 'daily-cleanup'
]);
```

## Cron Expression Syntax

The scheduler uses standard cron expression syntax with five fields:

```
*    *    *    *    *
┬    ┬    ┬    ┬    ┬
│    │    │    │    │
│    │    │    │    └── Day of week (0-7, where 0 and 7 are Sunday)
│    │    │    └─────── Month (1-12)
│    │    └──────────── Day of month (1-31)
│    └───────────────── Hour (0-23)
└────────────────────── Minute (0-59)
```

### Common Examples

| Expression | Description |
| --- | --- |
| `* * * * *` | Every minute |
| `0 * * * *` | Every hour |
| `0 0 * * *` | Daily at midnight |
| `0 2 * * *` | Daily at 2 AM |
| `0 0 * * 0` | Weekly on Sunday at midnight |
| `0 0 1 * *` | Monthly on the 1st at midnight |
| `*/5 * * * *` | Every 5 minutes |
| `0 9-17 * * 1-5` | Every hour from 9 AM to 5 PM on weekdays |

### Special Characters

- `*` - Matches any value
- `,` - List separator (e.g., `1,3,5`)
- `-` - Range (e.g., `1-5`)
- `/` - Step values (e.g., `*/15` for every 15 units)

## Running the Scheduler

The scheduler runs automatically when you have a worker running:

```bash
kirby queues:work
```

You can also run scheduled jobs manually to test them:

```bash
kirby queues:schedule
```

This will check for due jobs and queue them immediately, useful for testing your schedules.

## Timezone Configuration

By default, scheduled jobs use UTC. Configure your timezone in `config.php`:

```php
return [
  'tobimori.queues' => [
    'schedule' => [
      'timezone' => 'Europe/Berlin'
    ]
  ]
];
```

## Preventing Overlaps

By default, the scheduler prevents the same scheduled job from overlapping. If a job is still running when it's due again, the new instance will be skipped. You can change this behavior:

```php
return [
  'tobimori.queues' => [
    'schedule' => [
      'overlap' => true  // Allow overlapping executions
    ]
  ]
];
```


## Best Practices

### Use Appropriate Intervals

Don't schedule jobs more frequently than necessary. Consider the job's duration and server resources:

```php
// Good: Daily cleanup at night
Queues::schedule('0 3 * * *', CleanupJob::class);

// Bad: Heavy job every minute
Queues::schedule('* * * * *', HeavyProcessingJob::class);
```

### Handle Timezone Changes

Be aware of daylight saving time changes when scheduling critical jobs:

```php
// This might run twice or skip during DST changes
Queues::schedule('0 2 * * *', CriticalJob::class);

// Better: Use a time that doesn't change
Queues::schedule('0 4 * * *', CriticalJob::class);
```

### Monitor Scheduled Jobs

Check the Panel regularly to ensure your scheduled jobs are running as expected. Failed scheduled jobs will appear in the failed jobs list just like regular jobs.

### Test Your Schedules

Before deploying, test your cron expressions:

```php
// Test if a job would run at specific times
$cron = new \tobimori\Queues\Schedule\CronExpression('0 2 * * *');
echo $cron->isDue(); // true if it should run now
echo $cron->getNextRunDate(); // next execution time
```

## Limitations

- The scheduler requires at least one worker to be running
- Scheduling precision is limited to one minute
- If all workers are stopped, scheduled jobs won't run until a worker starts again
