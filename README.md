# Queues for Kirby

Queues is a simple but sophisticated queueing API for Kirby CMS inspired by Laravel's queueing system.

Its main goal is to provide plugin developers with a common way to implement background tasks without each plugin having to implement its own queueing system (of course you can also create background tasks unique to your Kirby site); e.g. [Kirby SEO](https://github.com/tobimori/kirby-seo) uses these for page audits, broken links checking; [DreamForm](https://github.com/tobimori/kirby-dreamform) allows you to run "actions" as background tasks to postpone long-running processes such as PDF generation or email sending.

Its storage is powered by the Kirby Caching API, which allows us to easily support a variety of providers, such as File, Redis, and more out of the box. Theoretically, this also supports multiple workers running in parallel on multiple machines, albeit the plugin has not been tested with such a setup.

Queues also supports scheduled jobs in the same worker process.

If the user has not setup a worker, tasks can be also executed immediately (which is subject of the limits of the PHP request lifecycle).


## Installation

Kirby Queues requires Composer, since it requires the use of the Kirby CLI. If you use Queues in your plugin, keep in mind that Queues also has to be installed separately by the user.

```
composer require tobimori/kirby-queues getkirby/cli
```

## Support

This plugin is provided free of charge & published under the permissive MIT License. If you use it in a commercial project or build your own commercial plugin using Queues, please consider to [sponsor me on GitHub](https://github.com/sponsors/tobimori) or purchase any of my [commercial plugins](https://plugins.andkindness.com/) to support further development and continued maintenance.

## License

[MIT License](./LICENSE)
Copyright © 2025 Tobias Möritz
