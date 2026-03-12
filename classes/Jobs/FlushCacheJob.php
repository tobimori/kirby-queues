<?php

namespace tobimori\Queues\Jobs;

use Kirby\Cms\App;
use Kirby\Toolkit\I18n;
use tobimori\Queues\Job;

/**
 * Flushes a Kirby cache
 */
class FlushCacheJob extends Job
{
	public function type(): string
	{
		return 'builtin:flush-cache';
	}

	public function name(): string
	{
		$name = I18n::translate('queues.job.flushCache');
		return is_string($name) ? $name : 'Flush Cache';
	}

	public function handle(): void
	{
		$name = $this->payload()['name'] ?? 'pages';

		App::instance()->cache($name)->flush();

		$this->log('info', "Cache \"{$name}\" has been flushed");
	}
}
