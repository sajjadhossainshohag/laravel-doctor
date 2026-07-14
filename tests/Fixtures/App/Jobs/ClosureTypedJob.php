<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Closure;

class ClosureTypedJob implements ShouldQueue
{
    public function __construct(
        public Closure $callback,
    ) {}

    public function handle(): void {}
}
