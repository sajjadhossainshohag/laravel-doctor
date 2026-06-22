<?php

namespace SajjadHossain\Doctor\Checks\Config;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class AbortIfWrongHttpCodeCheck implements HealthCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'abort_if() / abort_unless() Wrong HTTP Code';
    }

    public function category(): string
    {
        return 'config';
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

                // Strip PHP line comments and Blade {{-- --}} comments so we
                // don't flag commented-out code.
                $stripped = preg_replace('!//[^\n]*!', '', $content);
                $stripped = preg_replace('/\{\{--.*?--\}\}/s', '', $stripped);
                // Drop string literals so quoted HTTP codes don't get flagged.
                $stripped = preg_replace("/'[^'\\\\]*(?:\\\\.[^'\\\\]*)*'/", "''", $stripped);
                $stripped = preg_replace('/"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"/', '""', $stripped);

                // preg_match_all so EVERY occurrence per file is checked
                // (previously preg_match only inspected the first hit per file).
                $found = false;
                if (preg_match_all('/abort_if\s*\(\s*[^,]+,\s*(\d{3})/', $stripped, $m1)) {
                    foreach ($m1[1] as $code) {
                        if ($this->isBadHttpCode((int) $code)) {
                            $locations[] = [
                                'file' => $file->getRealPath(),
                                'issue' => "abort_if() with HTTP {$code} — expected 4xx/5xx error code",
                            ];
                            $found = true;
                        }
                    }
                }
                if (preg_match_all('/abort_unless\s*\(\s*[^,]+,\s*(\d{3})/', $stripped, $m2)) {
                    foreach ($m2[1] as $code) {
                        if ($this->isBadHttpCode((int) $code)) {
                            $locations[] = [
                                'file' => $file->getRealPath(),
                                'issue' => "abort_unless() with HTTP {$code} — expected 4xx/5xx error code",
                            ];
                            $found = true;
                        }
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
                message: 'No suspicious HTTP codes in abort_if/abort_unless calls.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' abort_if/abort_unless call(s) with potentially wrong code.',
            locations: $locations,
            suggestion: 'Use appropriate 4xx (client error) or 5xx (server error) codes.',
        );
    }

    private function isBadHttpCode(int $code): bool
    {
        // 1xx (informational) and 2xx/3xx (success/redirection) are not errors.
        // Only 4xx/5xx are appropriate for abort().
        return $code < 400;
    }
}