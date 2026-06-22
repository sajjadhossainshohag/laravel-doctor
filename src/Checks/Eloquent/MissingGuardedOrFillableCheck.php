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

                // Laravel's default $guarded = ['*'] already blocks all mass
                // assignment, so a model with no override is SAFE.
                // We only flag a model that explicitly sets $guarded = []
                // (or uses the Unguarded attribute) without declaring fillable
                // — that combination IS risky.
                $explicitlyEmptyGuarded = preg_match('/protected\s+\$\s*guarded\s*=\s*\[\s*\]/', $stripped);

                if ($hasFillable || $hasGuarded) {
                    // User has declared one of them — assume they know.
                    continue;
                }

                if ($explicitlyEmptyGuarded || $hasUnguardedAttr) {
                    // No $guarded or $fillable set, AND empty-guarded is
                    // explicitly opted into. Only flag if the user did NOT
                    // also declare $fillable to lock the model down.
                    if ($hasFillable) {
                        continue;
                    }
                    $locations[] = [
                        'file' => $file->getRealPath(),
                        'issue' => "Model '{$classM[1]}' has empty \$guarded (or #[Unguarded]) but no \$fillable — all attributes are mass-assignable",
                    ];
                }

                // Otherwise: no override at all. Laravel's default
                // $guarded = ['*'] is in effect — that is safe.
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
