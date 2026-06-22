<?php

namespace SajjadHossain\Doctor\Checks\Livewire;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class MissingLivewireComponentCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Missing Livewire Component Class';
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
        $paths = config('view.paths', [resource_path('views')]);

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
                // Match <livewire:foo>, <livewire:foo/>, <livewire:foo ...attrs>, etc.
// The original regex required whitespace after the name and missed the
// default idiomatic forms (no attrs, self-closing).
preg_match_all('/<livewire:([\w-]+)(?=[\s>\/])/', $content, $m);
                foreach ($m[1] as $component) {
                    $className = $this->componentToClass($component);
                    if (! class_exists($className)) {
                        $locations[] = [
                            'file' => $file->getRealPath(),
                            'issue' => "Livewire component '<livewire:{$component} />' class '{$className}' not found",
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
                message: 'All Livewire component classes exist.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' Livewire component(s) missing class file.',
            locations: $locations,
            suggestion: 'Create the Livewire component class or fix the component name.',
        );
    }

    private function componentToClass(string $name): string
    {
        $parts = explode('-', $name);
        $parts = array_map('ucfirst', $parts);
        $studly = implode('', $parts);

        $namespaces = [
            'App\\Livewire',
            'App\\Http\\Livewire',
        ];

        foreach ($namespaces as $ns) {
            $candidate = $ns.'\\'.$studly;
            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        return 'App\\Livewire\\'.$studly;
    }
}
