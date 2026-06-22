<?php

namespace SajjadHossain\Doctor\Checks\Eloquent;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class AccessorMutatorStyleConflictCheck implements HealthCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Accessor/Mutator Style Conflict';
    }

    public function category(): string
    {
        return 'eloquent';
    }

    public function severity(): Severity
    {
        return Severity::Info;
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

                // We only treat this as a problem if BOTH styles define an
                // accessor/mutator for the SAME attribute. Mixing styles
                // across different attributes is supported by Laravel.
                $oldNames = [];
                if (preg_match_all('/function\s+get(\w+)Attribute\s*\(/', $stripped, $m1)) {
                    $oldNames = array_map(fn ($n) => lcfirst($n), $m1[1]);
                }
                $newNames = [];
                if (preg_match_all('/Attribute::make\s*\([^)]*get\s*:/s', $stripped)) {
                    // crude — we report the file only if Attribute::make has any getter
                    $newNames = ['*'];
                }

                if (! empty($oldNames) && ! empty($newNames)) {
                    // Refine: check if any old-style accessor name ALSO has an
                    // Attribute::make getter for the same attribute name.
                    $conflicts = [];
                    foreach ($oldNames as $name) {
                        $studly = str_replace('_', '', ucwords($name, '_'));
                        if (preg_match('/Attribute::make\s*\(\s*[\'"]?get[\'"]?\s*:/s', $stripped)
                            && preg_match('/[\'"]?get[\'"]?\s*:\s*function\s*\(\s*\)\s*\{[^}]*\$this->'.preg_quote($name, '/').'\b/s', $stripped)) {
                            $conflicts[] = $name;
                        }
                    }

                    if (! empty($conflicts)) {
                        $locations[] = [
                            'file' => $file->getRealPath(),
                            'issue' => 'Model defines both old-style getXxxAttribute() and new-style Attribute::make() for the same attribute(s): '.implode(', ', $conflicts),
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
                message: 'No accessor/mutator style conflicts on the same attribute detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' model(s) define old and new accessor/mutator styles for the same attribute.',
            locations: $locations,
            suggestion: 'Pick one style per attribute. Mixing styles across different attributes is fine.',
        );
    }
}
