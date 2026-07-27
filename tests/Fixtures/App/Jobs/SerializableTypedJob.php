<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;

class SerializableTypedJob implements ShouldQueue
{
    public function __construct(
        public readonly int $userId,
    ) {}

    public function handle(): void {}
}
