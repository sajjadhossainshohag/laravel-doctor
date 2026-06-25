<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Jobs\Isolated\BackoffMethod;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Bug regression: $tries = 0 + backoff() method (no $tries cap) is
 * still UNSAFE. The backoff() method returns the delay between
 * retries but does NOT cap the retry count. Must still be flagged.
 */
class BackoffMethodJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 0;

    public function __construct(public readonly int $userId) {}

    public function handle(): void {}

    public function backoff(): int
    {
        return 30;
    }
}