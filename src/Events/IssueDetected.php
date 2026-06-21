<?php

namespace SajjadHossain\Doctor\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use SajjadHossain\Doctor\DTOs\CheckResult;

class IssueDetected
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly CheckResult $result,
    ) {}
}
