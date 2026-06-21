<?php

namespace SajjadHossain\Doctor\Checks\Container;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class SingletonAfterFirstResolveCheck implements HealthCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Singleton Registered in boot()';
    }

    public function category(): string
    {
        return 'container';
    }

    public function severity(): Severity
    {
        return Severity::Info;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: [app_path('Providers')];
        $ignore = config('doctor.ignore.container', []);

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

                $realPath = $file->getRealPath();
                if ($this->isIgnored($realPath, $ignore)) {
                    continue;
                }

                $content = file_get_contents($realPath);
                if (preg_match('/function\s+boot\s*\(/', $content) &&
                    preg_match('/\$this->app->singleton\s*\(/', $content)) {
                    $locations[] = [
                        'file' => $realPath,
                        'issue' => 'Singleton registered in boot() method',
                        'value' => 'Singletons in boot() may resolve after first use in register()',
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
                message: 'No singletons registered in boot() methods.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' singleton(s) registered in boot() — safe if not resolved earlier.',
            locations: $locations,
            suggestion: 'Move to register() if the binding may have been resolved before boot() runs.',
        );
    }

    private function isIgnored(string $path, array $patterns): bool
    {
        $normalized = str_replace('\\', '/', $path);
        foreach ($patterns as $pattern) {
            $normalizedPattern = str_replace('\\', '/', $pattern);
            if (fnmatch($normalizedPattern, $normalized) || str_contains($normalized, $normalizedPattern)) {
                return true;
            }
        }
        return false;
    }
}
