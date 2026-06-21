<?php

namespace SajjadHossain\Doctor\Checks\Validation;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class AuthorizeAlwaysFalseCheck implements HealthCheck
{
    public function name(): string
    {
        return 'authorize() Always Returns False';
    }

    public function category(): string
    {
        return 'validation';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = [app_path('Http/Requests')];

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
                if (preg_match('/function\s+authorize\s*\([^)]*\)\s*\{\s*return\s+false\s*;\s*\}/', $content)) {
                    $locations[] = [
                        'file' => $file->getRealPath(),
                        'issue' => 'authorize() always returns false — every request gets 403',
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
                message: 'No FormRequests with authorize() always returning false.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' FormRequest(s) with authorize() hardcoded to false.',
            locations: $locations,
            suggestion: 'Replace "return false" with actual authorization logic or "return true".',
        );
    }
}
