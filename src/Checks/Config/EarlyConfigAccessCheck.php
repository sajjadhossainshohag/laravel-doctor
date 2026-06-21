<?php

namespace SajjadHossain\Doctor\Checks\Config;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class EarlyConfigAccessCheck implements HealthCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'config() Called in register() Method';
    }

    public function category(): string
    {
        return 'config';
    }

    public function severity(): Severity
    {
        return Severity::Info;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: [app_path('Providers')];

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

                // Extract just the register() method body (greedy match across newlines)
                // so we don't false-positive on config() calls in boot() or other methods.
                if (! preg_match('/function\s+register\s*\([^)]*\)\s*\{(.*?)\n\s*\}/s', $content, $m)) {
                    continue;
                }

                $registerBody = $m[1];

                // Only flag if config() is actually called in the register() body.
                // Exclude: $config (variable), ->config (chain), 'config(' (string), //config (comment)
                if (preg_match('/(?<![$>\/\\\\])\bconfig\s*\(/', $registerBody)) {
                    $locations[] = [
                        'file' => $file->getRealPath(),
                        'issue' => 'config() called in register() method — config repository may not be fully loaded yet, prefer boot()',
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
                message: 'No early config access detected in service providers.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' service provider(s) access config() in register().',
            locations: $locations,
            suggestion: 'Move config() calls to boot() or use config() only after all providers are loaded.',
        );
    }
}
