<?php

namespace SajjadHossain\Doctor\Checks\Cache;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class RememberReturnsClosureCheck implements HealthCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Cache::remember() Returns Closure';
    }

    public function category(): string
    {
        return 'cache';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: config('doctor.scan_paths', [app_path(), resource_path('views')]);

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
                if (preg_match('/Cache::remember\s*\([^;]*function\s*\([^)]*\)\s*\{(?:[^}]*\bfunction\b[^}]*return\s+\$?\w+\s*;|[^}]*\breturn\s+(?:fn\s*\(|function\s*\())/', $content)) {
                    $locations[] = [
                        'file' => $file->getRealPath(),
                        'issue' => 'Cache::remember() callback returns a Closure — will fail on unserialize',
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
                message: 'No Cache::remember() callbacks returning Closures detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' remember() callback(s) may return a Closure.',
            locations: $locations,
            suggestion: 'Ensure the callback returns serializable data, not a Closure.',
        );
    }
}
