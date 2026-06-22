<?php

namespace SajjadHossain\Doctor\Checks\Schedule;

use Illuminate\Contracts\Console\Kernel;
use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class DeletedScheduledCommandCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Scheduled Command Name No Longer Registered';
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
        $artisanCommands = [];
        try {
            $artisan = app(Kernel::class);
            $artisanCommands = array_keys($artisan->all());
        } catch (\Throwable) {
        }

        $filesToScan = [];
        foreach ([app_path('Console'), app_path('Providers')] as $path) {
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
        $consoleRoute = base_path('routes/console.php');
        if (is_file($consoleRoute)) {
            $filesToScan[] = $consoleRoute;
        }

        foreach ($filesToScan as $filePath) {
            $content = file_get_contents($filePath);
            // Match both $schedule->command('name') and Schedule::command('name').
            // preg_match_all so every reference is checked.
            if (preg_match_all('/(?:\$schedule|Schedule::)->command\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
                foreach ($matches[1] as $name) {
                    if (! empty($artisanCommands) && ! in_array($name, $artisanCommands, true)) {
                        $locations[] = [
                            'file' => $filePath,
                            'issue' => "Scheduled command '{$name}' is not registered in Artisan",
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
                message: 'All scheduled command names are registered in Artisan.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' scheduled command name(s) not found in Artisan.',
            locations: $locations,
            suggestion: 'Register the command or fix the schedule reference.',
        );
    }
}
