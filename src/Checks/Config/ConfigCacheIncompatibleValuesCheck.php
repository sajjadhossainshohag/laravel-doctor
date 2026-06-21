<?php

namespace SajjadHossain\Doctor\Checks\Config;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class ConfigCacheIncompatibleValuesCheck implements HealthCheck
{
    private array $configPaths = [];

    public function withConfigPaths(array $paths): static
    {
        $this->configPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'config:cache Incompatible Values';
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
        $paths = $this->configPaths ?: [config_path()];

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

                // Match arrow-function assignments:  'key' => fn (...) => ...
                // OR closure assignments:           'key' => function (...) { ... }
                // Both forms assign a Closure to a config key, which breaks config:cache.
                $hasArrowFnAssign = preg_match('/=>\s*fn\s*\(/', $content);
                $hasClosureAssign = preg_match('/=>\s*function\s*\(/', $content);

                if ($hasArrowFnAssign || $hasClosureAssign) {
                    $locations[] = [
                        'file' => $file->getRealPath(),
                        'issue' => 'Config file assigns a Closure (arrow function or anonymous function) — breaks php artisan config:cache',
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
                message: 'No incompatible values in config files.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' config file(s) contain Closure/function definitions.',
            locations: $locations,
            suggestion: 'Move Closures to a service provider or use serializable values only.',
        );
    }
}
