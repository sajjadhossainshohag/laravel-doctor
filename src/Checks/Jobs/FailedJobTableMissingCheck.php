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
        $locations = [];

        // The failed_jobs table is only required when the configured
        // queue.failed.driver is the database-style driver. For null, file,
        // or DynamoDB, Laravel does not need the failed_jobs table.
        $failedDriver = config('queue.failed.driver', 'null');

        $needsTable = in_array($failedDriver, ['database', 'database-uuids'], true);

        if (! $needsTable) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: "queue.failed.driver is '{$failedDriver}' — no failed_jobs table is required.",
            );
        }

        $table = config('queue.failed.table', 'failed_jobs');

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
                message: "Failed jobs table '{$table}' exists.",
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
