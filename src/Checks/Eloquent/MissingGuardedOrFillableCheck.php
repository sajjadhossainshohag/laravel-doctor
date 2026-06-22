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
                $stripped = preg_replace('#/\*.*?\*/#s', '', $content);
                $stripped = preg_replace('!//[^\n]*!', '', $stripped);

                // Only consider actual Eloquent models. Skip abstract classes
                // and non-Model classes.
                if (! preg_match('/class\s+(\w+)\s+extends\s+Model\b/', $stripped, $classM)) {
                    continue;
                }

                $hasFillable = preg_match('/protected\s+\$\s*fillable\s*=/', $stripped);
                $hasGuarded = preg_match('/protected\s+\$\s*guarded\s*=/', $stripped);
                $hasUnguardedAttr = preg_match('/#\s*\[\s*Unguarded\b/', $stripped);
                $hasGuardedAttr = preg_match('/#\s*\[\s*Guarded\b/', $stripped);

                if ($hasFillable || $hasGuarded) {
                    // User has declared one of them — assume they know.
                    continue;
                }

                // Neither $fillable nor $guarded is declared. This is risky
                // because the model's mass-assignment behaviour is implicit
                // (Laravel's default $guarded = ['*'] applies), and the
                // developer may not have intended that. Flag it.
                $locations[] = [
                    'file' => $file->getRealPath(),
                    'issue' => "Model '{$classM[1]}' declares neither \$fillable nor \$guarded — mass-assignment behaviour is implicit and likely unintended",
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All models have appropriate mass-assignment protection.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' model(s) have unsafe mass-assignment configuration.',
            locations: $locations,
            suggestion: 'Add `protected $fillable = [...]` to limit which attributes can be mass-assigned.',
        );
    }
}
