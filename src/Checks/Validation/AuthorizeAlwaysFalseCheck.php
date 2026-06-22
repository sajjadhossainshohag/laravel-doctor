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
        return Severity::Info;
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
                $stripped = preg_replace('#/\*.*?\*/#s', '', $content);
                $stripped = preg_replace('!//[^\n]*!', '', $stripped);

                // authorize() { return false; } is documented and is a
                // legitimate, intentional pattern when the developer
                // wants to forbid all access in a FormRequest. The
                // previous version of this check flagged it as a hard
                // error. We surface it as informational instead.
                if (preg_match('/function\s+authorize\s*\([^)]*\)\s*\{\s*return\s+false\s*;\s*\}/', $stripped)) {
                    $locations[] = [
                        'file' => $file->getRealPath(),
                        'issue' => 'authorize() returns false — every request gets 403. Verify this is intentional.',
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
                message: 'No FormRequests with authorize() hardcoded to false detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: true,
            message: count($locations).' FormRequest(s) have authorize() hardcoded to false. This is informational — confirm this is intentional.',
            locations: $locations,
            suggestion: 'If this is unintended, replace "return false" with real authorization logic or "return true".',
        );
    }
}