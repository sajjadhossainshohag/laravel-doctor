<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Jobs\Isolated\BackoffOnly;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Bug regression: $tries = 0 + $backoff = N is NOT a safe combination.
 * $backoff is the delay between retries, not a retry cap. The check
 * must still flag this.
 */
class BackoffOnlyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 0;
    public $backoff = 30;

    public function __construct(public readonly int $userId) {}

    public function handle(): void {}
}