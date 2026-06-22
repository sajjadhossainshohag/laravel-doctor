<?php

namespace SajjadHossain\Doctor\Checks\Jobs;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class QueueDriverCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Queue Driver (sync)';
    }

    public function category(): string
    {
        return 'jobs';
    }

    public function severity(): Severity
    {
        return Severity::Info;
    }

    public function run(): CheckResult
    {
        $driver = config('queue.default');

        if ($driver === 'sync' && app()->environment('production')) {
            // sync in production is a configuration choice (often used
            // for very low-traffic / no-background-worker apps), not a
            // runtime correctness failure. Surface as informational rather
            // than failing the health check.
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'Queue driver is set to "sync" in production — queued jobs will execute synchronously.',
                locations: [['driver' => 'sync']],
                suggestion: 'If you need asynchronous processing, set QUEUE_CONNECTION to database, redis, or another async driver in production.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: true,
            message: "Queue driver is '{$driver}' — appropriate for the current environment.",
        );
    }
}
