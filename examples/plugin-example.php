<?php

use tobimori\Queues\Queues;

/**
 * Example of how to register a custom queue in a plugin
 */

// In your plugin's index.php or initialization code:
Kirby::plugin('yourname/yourplugin', [
    'hooks' => [
        'system.loadPlugins:after' => function () {
            // Register your custom queue(s)
            Queues::registerQueue('seo');
            Queues::registerQueue(['emails', 'exports', 'webhooks']);
            
            // Register your job classes
            Queues::register([
                YourPlugin\Jobs\SeoAnalysisJob::class,
                YourPlugin\Jobs\EmailJob::class,
                YourPlugin\Jobs\ExportJob::class,
                YourPlugin\Jobs\WebhookJob::class
            ]);
        }
    ]
]);

// Then you can push jobs to your custom queues:
queue(YourPlugin\Jobs\SeoAnalysisJob::class, [
    'pageId' => $page->id(),
    'checks' => ['meta', 'images', 'links']
], 'seo');

queue(YourPlugin\Jobs\EmailJob::class, [
    'to' => 'user@example.com',
    'template' => 'welcome'
], 'emails');

// The worker will automatically process jobs from all registered queues:
// kirby queues:work
// Will process: default, high, low, seo, emails, exports, webhooks

// Or you can specify specific queues:
// kirby queues:work seo,emails