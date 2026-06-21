<?php

namespace SajjadHossain\Doctor\Checks\Jobs;

use Illuminate\Support\Facades\Schema;
use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class FailedJobTableMissingCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Failed Jobs Table Missing';
    }

    public function category(): string
    {
        return 'jobs';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $table = 'failed_jobs';
        $locations = [];

        try {
            if (! Schema::hasTable($table)) {
                $locations[] = [
                    'issue' => "'{$table}' table does not exist — failed jobs will cause secondary exceptions",
                ];
            }
        } catch (\Throwable $e) {
            $locations[] = [
                'issue' => "Could not check '{$table}' table: {$e->getMessage()}",
            ];
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'Failed jobs table exists.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: 'Failed jobs table is missing.',
            locations: $locations,
            suggestion: 'Run "php artisan queue:failed-table" migration and "php artisan migrate".',
        );
    }
}
