<?php

namespace SajjadHossain\Doctor\Checks\Gates;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class MissingPolicyClassCheck implements HealthCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Policy Registered But Class Missing';
    }

    public function category(): string
    {
        return 'gates';
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
                $stripped = preg_replace('#/\*.*?\*/#s', '', $content);
                $stripped = preg_replace('!//[^\n]*!', '', $stripped);

                preg_match('/^\s*namespace\s+([\w\\\\]+);/m', $stripped, $nsM);
                $namespace = $nsM[1] ?? '';

                preg_match_all(
                    '/Gate::policy\s*\(\s*([\w\\\\]+)::class\s*,\s*([\w\\\\]+)::class\s*\)/',
                    $stripped,
                    $matches,
                    PREG_SET_ORDER
                );

                foreach ($matches as $m) {
                    $policyClass = $m[2];

                    // Try direct FQCN first.
                    if (class_exists($policyClass)) {
                        continue;
                    }

                    // Try resolving against `use` imports in the file.
                    $resolved = $this->resolveClassName($stripped, $policyClass);
                    if ($resolved !== null && class_exists($resolved)) {
                        continue;
                    }

                    // Also try same-namespace resolution.
                    if ($namespace !== '' && ! str_contains($policyClass, '\\')) {
                        $candidate = $namespace.'\\'.$policyClass;
                        if (class_exists($candidate)) {
                            continue;
                        }
                    }

                    // Conventional App\Policies\ fallback.
                    if (! str_contains($policyClass, '\\')) {
                        $prefixed = 'App\\Policies\\'.$policyClass;
                        if (class_exists($prefixed)) {
                            continue;
                        }
                    }

                    $locations[] = [
                        'file' => $file->getRealPath(),
                        'issue' => "Policy class '{$policyClass}' does not exist",
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
                message: 'All registered policy classes exist.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' registered policy class(es) not found.',
            locations: $locations,
            suggestion: 'Create the missing policy class or remove the Gate::policy() registration.',
        );
    }

    /**
     * Resolve a possibly-short class name against `use` imports in the file.
     */
    private function resolveClassName(string $content, string $class): ?string
    {
        $class = ltrim($class, '\\');
        if (class_exists($class)) {
            return $class;
        }

        if (preg_match_all('/^\s*use\s+([\w\\\\]+)(?:\s+as\s+\w+)?\s*;/m', $content, $uses)) {
            foreach ($uses[1] as $fqcn) {
                $parts = explode('\\', ltrim($fqcn, '\\'));
                $short = end($parts);
                if ($short === $class) {
                    return ltrim($fqcn, '\\');
                }
            }
        }

        return null;
    }
}
