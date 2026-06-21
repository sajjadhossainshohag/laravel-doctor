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
                if (preg_match('/abort_if\s*\([^,]+,\s*(\d{3})/', $content, $m) ||
                    preg_match('/abort_unless\s*\([^,]+,\s*(\d{3})/', $content, $m)) {
                    $code = (int) $m[1];
                    if ($code < 400 && $code !== 200) {
                        $locations[] = [
                            'file' => $file->getRealPath(),
                            'issue' => "abort_if/abort_unless with HTTP {$code} — expected 4xx/5xx error code",
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
}
