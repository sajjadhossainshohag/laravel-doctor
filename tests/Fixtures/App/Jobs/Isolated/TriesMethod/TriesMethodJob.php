<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Jobs\Isolated\TriesMethod;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * $tries = 0 with tries() method — INTENTIONAL. The tries() method
 * overrides the property and provides the actual retry cap.
 * Must NOT be flagged.
 */
class TriesMethodJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 0;

    public function __construct(public readonly int $userId) {}

    public function handle(): void {}

    public function tries(): int
    {
        return 5;
    }
}