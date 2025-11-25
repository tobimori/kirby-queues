---
title: Hooks
---

The plugin triggers hooks at key points in the worker and job lifecycle.

## Available Hooks

| Hook | Arguments |
| --- | --- |
| `tobimori.queues.worker:before` | `$worker`, `$queues` |
| `tobimori.queues.worker:after` | `$worker`, `$queues`, `$jobsProcessed` |
| `tobimori.queues.job:before` | `$job`, `$worker` |
| `tobimori.queues.job:after` | `$job`, `$worker`, `$result` |
| `tobimori.queues.job:failed` | `$job`, `$worker`, `$exception`, `$willRetry` |

Wildcards are supported: `tobimori.queues.*:*`, `tobimori.queues.job:*`, etc.

## Registering Scheduled Jobs

If you want to register scheduled jobs based on panel settings or data that needs to be loaded, and want to avoid the resource overhead on each request, use the `worker:before` hook:

```php
'hooks' => [
    'tobimori.queues.worker:before' => function () {
        Queues::register(MyJob::class);
        Queues::schedule('0 * * * *', MyJob::class);
    }
]
```
