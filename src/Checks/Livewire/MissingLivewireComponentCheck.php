<?php

namespace SajjadHossain\Doctor\Checks\Livewire;

use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class MissingLivewireComponentCheck extends PhpAstCheck
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

        foreach ($this->scanPhpFiles($paths) as $file) {
            preg_match_all('/<livewire:([\w-]+)(?=[\s>\/])/', $file['content'], $m);
            foreach ($m[1] as $component) {
                $className = $this->componentToClass($component);
                if (! class_exists($className)) {
                    $locations[] = [
                        'file' => $file['path'],
                        'issue' => "Livewire component '<livewire:{$component} />' class '{$className}' not found",
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
