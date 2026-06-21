<?php

namespace SajjadHossain\Doctor\Checks\Schedule;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class OverlappingJobsWithoutLockCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Overlapping Scheduled Jobs Without Lock';
    }

    public function category(): string
    {
        return 'schedule';
    }

    public function severity(): Severity
    {
        return Severity::Info;
    }

    public function run(): CheckResult
    {
        $locations = [];
        // Schedules can live in app/Console/* and in any service provider's
        // boot() method that calls $schedule->...
        $paths = [
            app_path('Console'),
            app_path('Providers'),
        ];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $content = file_get_contents($file->getRealPath());

                // Skip non-schedule providers.
                if (! preg_match('/\$schedule->(command|exec|call|job)/', $content)) {
                    continue;
                }

                $hasWithoutOverlap = (bool) preg_match('/->withoutOverlapping\s*\(/', $content);

                // Frequent triggers that risk overlapping executions:
                //  - every-minute family
                //  - ->cron('* * * * *') (any pattern with wildcard minutes)
                $hasFrequent = preg_match(
                    '/->(?:everyMinute|everyFiveMinutes|everyTenMinutes|everyFifteenMinutes|everyThirtyMinutes)\s*\(/',
                    $content
                ) || preg_match("/->cron\s*\(\s*[\'\"][^\'\"]*\*[^\'\"]*[\'\"]\s*\)/", $content);

                if ($hasFrequent && ! $hasWithoutOverlap) {
                    $locations[] = [
                        'file' => $file->getRealPath(),
                        'issue' => 'Frequent scheduled task (every-minute class or wildcard cron) without ->withoutOverlapping() — risk of overlapping runs',
                    ];
                }
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No overlapping scheduled jobs without lock detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' frequent task(s) without ->withoutOverlapping().',
            locations: $locations,
            suggestion: 'Add ->withoutOverlapping() to prevent task stacking.',
        );
    }
}
