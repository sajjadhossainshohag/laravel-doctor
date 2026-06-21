<?php

namespace SajjadHossain\Doctor\Checks\Livewire;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class MissingLivewireActionMethodCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Livewire Action Method Missing';
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

        // Build a map: short component name => list of public method names.
        $components = $this->indexComponents($componentPaths);
        $methodNamesByComponent = [];
        foreach ($components as $shortName => $className) {
            $methodNamesByComponent[$shortName] = $this->collectMethodNames($className);
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

                // Determine which component this view belongs to via <livewire:foo.bar />.
                // If none, skip — we can't tell which component to check against.
                if (! preg_match('/<livewire:([\w.-]+)/', $content, $cm)) {
                    continue;
                }

                $shortName = explode('.', $cm[1])[0];
                $componentMethods = $methodNamesByComponent[$shortName] ?? null;
                if ($componentMethods === null) {
                    continue;
                }

                preg_match_all('/wire:click\s*=\s*[\'"](\w+)/', $content, $m);
                foreach ($m[1] as $action) {
                    if (! in_array($action, $componentMethods, true)) {
                        $locations[] = [
                            'file' => $file->getRealPath(),
                            'issue' => "wire:click=\"{$action}\" — method not found on Livewire component '{$shortName}'",
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
                message: 'All wire:click actions have corresponding methods.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' wire:click action(s) with no matching method.',
            locations: $locations,
            suggestion: 'Add the action method to the corresponding Livewire component.',
        );
    }

    private function collectComponentMethods(array $paths): array
    {
        // Kept for BC: returns the flat list of all method names across all components.
        $methods = [];
        $components = $this->indexComponents($paths);
        foreach ($components as $className) {
            foreach ($this->collectMethodNames($className) as $method) {
                $methods[] = $method;
            }
        }

        return $methods;
    }

    /**
     * Build map: short name (StudlyCase of filename) => FQCN, by scanning component paths.
     *
     * @return array<string, string>
     */
    private function indexComponents(array $paths): array
    {
        $index = [];
        $namespaces = [
            'App\\Livewire',
            'App\\Http\\Livewire',
        ];

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

                // Try to detect FQCN via declared namespace.
                $class = null;
                if (preg_match('/^namespace\s+([\w\\\\]+);/m', $content, $ns)
                    && preg_match('/class\s+(\w+)/', $content, $cm)) {
                    $class = ltrim($ns[1] . '\\' . $cm[1], '\\');
                }

                if (! $class) {
                    continue;
                }

                $short = class_basename($class);
                $index[$short] = $class;
            }
        }

        return $index;
    }

    /**
     * @return array<int, string>
     */
    private function collectMethodNames(string $className): array
    {
        $methods = [];
        if (! class_exists($className)) {
            return $methods;
        }
        try {
            $reflection = new \ReflectionClass($className);
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isStatic() || $method->isAbstract()) {
                    continue;
                }
                if (in_array($method->getName(), ['__construct', 'mount', 'render', 'boot', 'booted', 'initializeTraits'], true)) {
                    continue;
                }
                // Only methods declared on the class or its parents (not inherited from Component base).
                if ($method->getDeclaringClass()->getName() === 'Livewire\Component') {
                    continue;
                }
                $methods[] = $method->getName();
            }
        } catch (\Throwable) {
            // ignore — class probably not autoloaded yet
        }

        return $methods;
    }
}
