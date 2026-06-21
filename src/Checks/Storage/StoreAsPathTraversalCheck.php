<?php

namespace SajjadHossain\Doctor\Checks\Storage;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class StoreAsPathTraversalCheck implements HealthCheck
{
    public function name(): string
    {
        return '->storeAs() Path Traversal Risk';
    }

    public function category(): string
    {
        return 'storage';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = config('doctor.scan_paths', [app_path(), resource_path('views')]);

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
                if (preg_match('/->storeAs\s*\([^)]*\.\..*\)/', $content)) {
                    $locations[] = [
                        'file' => $file->getRealPath(),
                        'issue' => '->storeAs() path contains ".." — may store to unexpected location',
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
                message: 'No path traversal risks in storeAs() calls.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' storeAs() call(s) with ".." in path.',
            locations: $locations,
            suggestion: 'Avoid ".." in storeAs() paths. Use a dedicated disk or sanitize the path.',
        );
    }
}
