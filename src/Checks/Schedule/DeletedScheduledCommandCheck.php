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
        $paths = [app_path('Console')];
        $artisanCommands = [];
        try {
            $artisan = app(Kernel::class);
            $artisanCommands = array_keys($artisan->all());
        } catch (\Throwable) {
        }

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
                if (preg_match('/\$schedule->command\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
                    $name = $m[1];
                    if (! empty($artisanCommands) && ! in_array($name, $artisanCommands, true)) {
                        $locations[] = [
                            'file' => $file->getRealPath(),
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
