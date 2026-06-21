<?php

namespace SajjadHossain\Doctor\Checks\Schedule;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class ScheduledCommandNotExistsCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Scheduled Command Class Does Not Exist';
    }

    public function category(): string
    {
        return 'schedule';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = [app_path('Console')];

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
                if (preg_match('/\$schedule->command\s*\(\s*([\w\\\\]+)::class/', $content, $m)) {
                    $commandClass = $m[1];
                    if (! class_exists($commandClass)) {
                        $locations[] = [
                            'file' => $file->getRealPath(),
                            'issue' => "Scheduled command class '{$commandClass}' does not exist",
                        ];
                    }
                }
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All scheduled command classes exist.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' scheduled command class(es) not found.',
            locations: $locations,
            suggestion: 'Create the command class or fix the reference in the schedule.',
        );
    }
}
