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
                if (preg_match_all('/\$this->app->bind\(\s*([\w\\\\]+)::class\s*,\s*([\w\\\\]+)::class\s*\)/', $content, $m)) {
                    foreach ($m[2] as $concrete) {
                        if (! class_exists($concrete)) {
                            $locations[] = [
                                'file' => $file->getRealPath(),
                                'issue' => "Binding references non-existent class '{$concrete}'",
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
}
