<?php

namespace SajjadHossain\Doctor\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use SajjadHossain\Doctor\DTOs\HealthScore;

class ScanCompleted
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly HealthScore $healthScore,
    ) {}
}
