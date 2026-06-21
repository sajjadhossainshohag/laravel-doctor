<?php

namespace SajjadHossain\Doctor\Checks\Events;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class ListenerMissingHandleMethodCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Listener Missing handle() Method';
    }

    public function category(): string
    {
        return 'events';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = [app_path('Listeners')];

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
                if (! preg_match('/function\s+handle\s*\(/', $content)) {
                    preg_match('/class\s+(\w+)/', $content, $m);
                    $locations[] = [
                        'file' => $file->getRealPath(),
                        'issue' => empty($m) ? 'Listener missing handle() method' : "Listener '{$m[1]}' missing handle() method",
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
                message: 'All listener classes have a handle() method.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' listener(s) missing handle() method.',
            locations: $locations,
            suggestion: 'Add a handle() method to the listener class.',
        );
    }
}
