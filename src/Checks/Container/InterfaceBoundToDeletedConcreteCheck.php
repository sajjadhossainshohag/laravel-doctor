<?php

namespace SajjadHossain\Doctor\Checks\Container;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class InterfaceBoundToDeletedConcreteCheck implements HealthCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Interface Bound to Deleted/Renamed Concrete';
    }

    public function category(): string
    {
        return 'container';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
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

                // Strip line/block comments so we don't flag commented bindings.
                $stripped = preg_replace('#/\*.*?\*/#s', '', $content);
                $stripped = preg_replace('!//[^\n]*!', '', $stripped);

                // Capture both the abstract (interface) and concrete. Note the
                // concrete may be a closure, a string, an instance, or
                // `SomeClass::class` — we only care about the ::class form.
                if (preg_match_all(
                    '/\$this->app->bind\s*\(\s*([\w\\\\]+)::class\s*,\s*([\w\\\\]+)::class\s*\)/',
                    $stripped,
                    $m
                )) {
                    foreach ($m[2] as $i => $concrete) {
                        $resolved = $this->resolveClassName($stripped, $concrete);
                        if ($resolved !== null && ! class_exists($resolved)) {
                            $locations[] = [
                                'file' => $file->getRealPath(),
                                'issue' => "Binding references non-existent class '{$resolved}' (was '{$concrete}')",
                            ];
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
                message: 'All container bindings reference existing classes.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' binding(s) reference non-existent class(es).',
            locations: $locations,
            suggestion: 'Fix the binding to reference an existing class, or remove the binding.',
        );
    }

    /**
     * Resolve a class name (which may be a short alias imported via `use`) to
     * its fully-qualified form. Returns the FQCN, or the original value if
     * it already looked like a FQCN. Returns null if it cannot be resolved.
     */
    private function resolveClassName(string $content, string $class): ?string
    {
        $class = ltrim($class, '\\');

        // If it's already a real FQCN, use as-is.
        if (class_exists($class)) {
            return $class;
        }

        // Resolve via `use` imports in the file.
        if (preg_match_all('/^\s*use\s+([\w\\\\]+)(?:\s+as\s+\w+)?\s*;/m', $content, $uses)) {
            foreach ($uses[1] as $fqcn) {
                $parts = explode('\\', ltrim($fqcn, '\\'));
                $short = end($parts);
                if ($short === $class) {
                    return ltrim($fqcn, '\\');
                }
            }
        }

        // Try same-namespace resolution.
        if (preg_match('/^\s*namespace\s+([\w\\\\]+);/m', $content, $ns)) {
            $candidate = $ns[1] . '\\' . $class;
            return $candidate;
        }

        // Last resort: return the short name; class_exists will be called on it.
        return $class;
    }
}
