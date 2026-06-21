<?php

namespace SajjadHossain\Doctor\Checks\Eloquent;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class MissingGuardedOrFillableCheck implements HealthCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Missing $guarded or $fillable';
    }

    public function category(): string
    {
        return 'eloquent';
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: [app_path('Models')];

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

                // Only consider actual Eloquent models. Skip abstract classes and non-Model classes.
                if (! preg_match('/class\s+(\w+)\s+extends\s+Model\b/', $content, $classM)) {
                    continue;
                }

                $hasFillable = preg_match('/protected\s+\$\s*fillable\s*=/', $content);
                $hasGuardedEmpty = preg_match('/protected\s+\$\s*guarded\s*=\s*\[\s*\]/', $content);
                $hasGuardedWildcard = preg_match('/protected\s+\$\s*guarded\s*=\s*\[\s*[\'"]\*[\'"]\s*\]/', $content);
                $hasGuardedList = preg_match('/protected\s+\$\s*guarded\s*=\s*\[/', $content);

                if ($hasFillable || $hasGuardedEmpty || $hasGuardedWildcard || $hasGuardedList) {
                    continue;
                }

                $locations[] = [
                    'file' => $file->getRealPath(),
                    'issue' => "Model '{$classM[1]}' has no \$fillable or \$guarded property — mass-assignment protection missing",
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All models have mass-assignment protection.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' model(s) missing mass-assignment protection.',
            locations: $locations,
            suggestion: 'Add `protected $fillable = [...]`, `protected $guarded = [...]`, or `protected $guarded = ["*"]` to each model to prevent mass-assignment vulnerabilities.',
        );
    }
}
