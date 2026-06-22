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
        $filesToScan = $this->collectScheduleFiles();

        foreach ($filesToScan as $filePath) {
            $content = file_get_contents($filePath);
            // Match both $schedule->command(Foo::class) and
            // Schedule::command(Foo::class). Use preg_match_all so we
            // catch every reference in the file, not just the first.
            if (preg_match_all('/(?:\$schedule|Schedule::)->command\s*\(\s*([\w\\\\]+)::class/', $content, $matches)) {
                foreach ($matches[1] as $commandClass) {
                    if (! class_exists($commandClass)) {
                        $locations[] = [
                            'file' => $filePath,
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

    /**
     * Collect PHP files that may contain schedule definitions: app/Console,
     * app/Providers (boot() method), and routes/console.php.
     *
     * @return list<string>
     */
    private function collectScheduleFiles(): array
    {
        $files = [];
        foreach ([app_path('Console'), app_path('Providers')] as $path) {
            if (! is_dir($path)) {
                continue;
            }
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iter as $file) {
                if ($file->getExtension() === 'php') {
                    $files[] = $file->getRealPath();
                }
            }
        }
        $consoleRoute = base_path('routes/console.php');
        if (is_file($consoleRoute)) {
            $files[] = $consoleRoute;
        }

        return $files;
    }
}
