<?php

namespace SajjadHossain\Doctor\Checks\Livewire;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class WireModelMissingPropertyCheck implements HealthCheck
{
    public function name(): string
    {
        return 'wire:model Binds to Non-Existent Property';
    }

    public function category(): string
    {
        return 'livewire';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $componentPaths = [app_path('Livewire')];

        $components = $this->indexComponents($componentPaths);
        $propertiesByComponent = [];
        foreach ($components as $shortName => $className) {
            $propertiesByComponent[$shortName] = $this->collectPropertyNames($className);
        }

        foreach ($components as $shortName => $className) {
            $componentView = $this->resolveComponentView($className, $shortName);
            if ($componentView === null || ! is_file($componentView)) {
                continue;
            }

            $content = file_get_contents($componentView);
            // Match wire:model, wire:model.lazy, .defer, .live (any modifier).
            preg_match_all('/wire:model(?:\.[a-z]+)?\s*=\s*[\'"]?([\w.\-]+)[\'"\s>]/', $content, $m);
            foreach ($m[1] as $target) {
                // Nested / form-object bindings (foo.bar or foo-bar-array
                // syntax for nested data) are valid even when no single
                // top-level property matches. Skip dotted / hyphenated.
                if (str_contains($target, '.') || str_contains($target, '-')) {
                    continue;
                }

                $componentProps = $propertiesByComponent[$shortName] ?? [];
                if (! in_array($target, $componentProps, true)) {
                    $locations[] = [
                        'file' => $componentView,
                        'component' => $className,
                        'issue' => "wire:model=\"{$target}\" — property may not exist on Livewire component '{$shortName}'",
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
                message: 'All wire:model bindings appear to match component properties.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' wire:model binding(s) may reference non-existent properties.',
            locations: $locations,
            suggestion: 'Add the missing property to the Livewire component class.',
        );
    }

    /**
     * @return array<string, string>
     */
    private function indexComponents(array $paths): array
    {
        $index = [];
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

                if (preg_match('/^namespace\s+([\w\\\\]+);/m', $content, $ns)
                    && preg_match('/class\s+(\w+)/', $content, $cm)) {
                    $class = ltrim($ns[1] . '\\' . $cm[1], '\\');
                    $index[class_basename($class)] = $class;
                }
            }
        }

        return $index;
    }

    private function resolveComponentView(string $className, string $shortName): ?string
    {
        try {
            $reflection = new \ReflectionClass($className);
            if ($reflection->hasProperty('view')) {
                $defaults = $reflection->getDefaultProperties();
                $view = $defaults['view'] ?? null;
                if (is_string($view) && $view !== '') {
                    $hints = config('view.paths', [resource_path('views')]);
                    foreach ($hints as $hint) {
                        $candidate = $hint.'/'.str_replace('.', '/', $view).'.blade.php';
                        if (file_exists($candidate)) {
                            return $candidate;
                        }
                    }
                }
            }
        } catch (\Throwable) {
        }

        $kebab = strtolower(preg_replace('/(?<!^)([A-Z])/', '-$1', $shortName));
        $conventional = resource_path('views/livewire/'.$kebab.'.blade.php');
        if (file_exists($conventional)) {
            return $conventional;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function collectPropertyNames(string $className): array
    {
        $props = [];
        if (! class_exists($className)) {
            return $props;
        }
        try {
            $reflection = new \ReflectionClass($className);
            foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
                if ($prop->isStatic()) {
                    continue;
                }
                $props[] = $prop->getName();
            }
        } catch (\Throwable) {
            // ignore
        }

        return $props;
    }
}
