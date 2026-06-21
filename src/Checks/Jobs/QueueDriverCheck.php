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
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: false,
                message: 'Queue driver is set to "sync" in production. Queued jobs will execute synchronously.',
                locations: [['driver' => 'sync']],
                suggestion: 'Set QUEUE_CONNECTION to database, redis, or another async driver in production.',
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
