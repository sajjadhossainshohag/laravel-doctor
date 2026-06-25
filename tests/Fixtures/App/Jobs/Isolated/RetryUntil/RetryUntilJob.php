<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Jobs\Isolated\RetryUntil;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * $tries = 0 with retryUntil() — INTENTIONAL. retryUntil() returns a
 * timestamp after which retries stop, so this IS a valid retry cap.
 * Must NOT be flagged.
 */
class RetryUntilJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 0;

    public function __construct(public readonly int $userId) {}

    public function handle(): void {}

    public function retryUntil(): \DateTime
    {
        return now()->addHour();
    }
}