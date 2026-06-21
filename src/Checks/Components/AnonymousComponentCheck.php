<?php

namespace SajjadHossain\Doctor\Checks\Components;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class AnonymousComponentCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Anonymous Component Paths';
    }

    public function category(): string
    {
        return 'components';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $namespaces = app('blade.compiler')->getAnonymousComponentNamespaces();
        $locations = [];

        foreach ($namespaces as $namespace => $directories) {
            foreach ((array) $directories as $dir) {
                if (!is_dir($dir)) {
                    $locations[] = [
                        'namespace' => $namespace,
                        'path' => $dir,
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
                message: 'All anonymous component namespace paths exist.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' anonymous component path(s) not found.',
            locations: $locations,
        );
    }
}
