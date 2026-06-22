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
        // Schedules can live in:
        //   - app/Console/Kernel.php (L10 and earlier)
        //   - app/Console/Commands/* (L11+ individual command schedules)
        //   - app/Providers/* boot() method that calls $schedule->...
        //   - routes/console.php (L11+ declarative schedules via
        //     Schedule::command(...) / use (function ($schedule) {...}) )
        $paths = [
            app_path('Console'),
            app_path('Providers'),
        ];
        $filesToScan = [];
        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iter as $file) {
                if ($file->getExtension() === 'php') {
                    $filesToScan[] = $file->getRealPath();
                }
            }
        }
        // routes/console.php is a single file (not a directory).
        $consoleRoute = base_path('routes/console.php');
        if (is_file($consoleRoute)) {
            $filesToScan[] = $consoleRoute;
        }

        foreach ($filesToScan as $filePath) {
            $content = file_get_contents($filePath);

            // Match either $schedule->... or Schedule::... facade calls.
            if (! preg_match('/(?:\$schedule|Schedule::)->(command|exec|call|job)\b/', $content)) {
                continue;
            }

            $hasWithoutOverlap = (bool) preg_match('/->withoutOverlapping\s*\(/', $content);

            // Frequent triggers that risk overlapping executions:
            //  - every-minute family
            //  - ->cron(...) with a wildcard in the MINUTES field. Cron is
            //    `minute hour day month weekday`; we only flag minute wild
            //    (position 1) — wildcard hour ('0 * * * *' = hourly) is NOT
            //    frequent enough to warrant overlap protection by default.
            $hasFrequent = (bool) preg_match(
                '/->(?:everyMinute|everyFiveMinutes|everyTenMinutes|everyFifteenMinutes|everyThirtyMinutes)\s*\(/',
                $content
            );

            // Detect cron with a wildcard in the minute slot only.
            // Match the first quoted segment and check if the FIRST space-
            // separated field contains a wildcard.
            if (preg_match_all('/->cron\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $cronMatches)) {
                foreach ($cronMatches[1] as $cronExpr) {
                    $fields = preg_split('/\s+/', trim($cronExpr));
                    if (isset($fields[0]) && str_contains($fields[0], '*')) {
                        $hasFrequent = true;
                        break;
                    }
                }
            }

            if ($hasFrequent && ! $hasWithoutOverlap) {
                $locations[] = [
                    'file' => $filePath,
                    'issue' => 'Frequent scheduled task (every-minute class or minute-wildcard cron) without ->withoutOverlapping() — risk of overlapping runs',
                ];
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
