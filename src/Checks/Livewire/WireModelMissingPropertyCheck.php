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
        $viewPaths = config('view.paths', [resource_path('views')]);
        $componentPaths = [app_path('Livewire')];

        // Index components and their public properties keyed by short name.
        $propertiesByComponent = [];
        foreach ($this->indexComponents($componentPaths) as $shortName => $className) {
            $propertiesByComponent[$shortName] = $this->collectPropertyNames($className);
        }

        foreach ($viewPaths as $path) {
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

                // Determine the component via <livewire:foo.bar /> (or @livewire('foo'))
                $shortName = null;
                if (preg_match('/<livewire:([\w.-]+)/', $content, $cm)) {
                    $shortName = explode('.', $cm[1])[0];
                } elseif (preg_match('/@livewire\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $cm2)) {
                    $shortName = explode('.', $cm2[1])[0];
                }

                if ($shortName === null || ! isset($propertiesByComponent[$shortName])) {
                    continue;
                }

                $componentProps = $propertiesByComponent[$shortName];

                preg_match_all('/wire:model(?:\.lazy|\.defer|\.live)?\s*=\s*[\'"]?([\w.-]+)[\'"\s>]/', $content, $m);
                foreach ($m[1] as $prop) {
                    if (str_contains($prop, '.')) {
                        continue;
                    }
                    if (! in_array($prop, $componentProps, true)) {
                        $locations[] = [
                            'file' => $file->getRealPath(),
                            'issue' => "wire:model=\"{$prop}\" — property may not exist on Livewire component '{$shortName}'",
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

    private function collectComponentProperties(array $paths): array
    {
        // Kept for BC.
        $props = [];
        foreach ($this->indexComponents($paths) as $className) {
            foreach ($this->collectPropertyNames($className) as $prop) {
                $props[] = $prop;
            }
        }

        return $props;
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
