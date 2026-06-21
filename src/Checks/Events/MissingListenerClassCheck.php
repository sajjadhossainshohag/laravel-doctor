<?php

namespace SajjadHossain\Doctor\Checks\Events;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class MissingListenerClassCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Missing Listener Class';
    }

    public function category(): string
    {
        return 'events';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = [app_path('Providers')];

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
                preg_match_all('/\b(\w+Listener\w*)\b/', $content, $listeners);
                foreach (array_unique($listeners[1]) as $listener) {
                    $fqcn = $this->resolveClass($content, $listener);
                    if ($fqcn && ! class_exists($fqcn)) {
                        $locations[] = [
                            'file' => $file->getRealPath(),
                            'issue' => "Listener class '{$fqcn}' does not exist",
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
                message: 'All registered listener classes exist.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' listener class(es) not found.',
            locations: $locations,
            suggestion: 'Create the missing listener class or remove the registration.',
        );
    }

    private function resolveClass(string $content, string $class): ?string
    {
        if (class_exists($class)) {
            return $class;
        }

        // Look for a `use` import that imports this short class name.
        if (preg_match_all('/^use\s+([\w\\\\]+)\s*;/m', $content, $uses)) {
            foreach ($uses[1] as $fqcn) {
                if ($this->shortName($fqcn) === ltrim($class, '\\')) {
                    return ltrim($fqcn, '\\');
                }
            }
        }

        // Try same-namespace resolution.
        if (preg_match('/^namespace\s+([\w\\\\]+);/m', $content, $ns)) {
            $candidate = $ns[1] . '\\' . ltrim($class, '\\');
            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', ltrim($fqcn, '\\'));

        return end($parts);
    }
}
