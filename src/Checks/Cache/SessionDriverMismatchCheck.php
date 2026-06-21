<?php

namespace SajjadHossain\Doctor\Checks\Cache;

use Illuminate\Support\Facades\Schema;
use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class SessionDriverMismatchCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Session Driver Mismatch';
    }

    public function category(): string
    {
        return 'cache';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $driver = config('session.driver', 'file');
        $locations = [];

        if ($driver === 'database') {
            $table = config('session.table', 'sessions');
            if (! Schema::hasTable($table)) {
                $locations[] = [
                    'issue' => "Database session driver configured but '{$table}' table does not exist",
                    'value' => "Session driver: {$driver}, table: {$table}",
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: "Session driver '{$driver}' appears correctly configured.",
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: 'Session driver configuration issue detected.',
            locations: $locations,
            suggestion: 'Run the session table migration or switch to a different session driver.',
        );
    }
}
