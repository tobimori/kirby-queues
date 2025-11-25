---
title: Built-in Jobs
---

The plugin ships with ready-to-use jobs for common tasks. These are automatically registered.

## Flush Cache

Flushes a Kirby cache by name.

| | |
| --- | --- |
| **Type** | `builtin:flush-cache` |
| **Class** | `tobimori\Queues\Jobs\FlushCacheJob` |
| **Payload** | `name` (string, default: `pages`) - the cache to flush |

```php
use tobimori\Queues\Jobs\FlushCacheJob;

// flush the pages cache every night at 3 AM
Queues::schedule('0 3 * * *', FlushCacheJob::class);

// flush a custom cache
queue(FlushCacheJob::class, ['name' => 'api']);
```
