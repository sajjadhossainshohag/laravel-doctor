<?php

namespace SajjadHossain\Doctor\Checks\Components;

use Illuminate\Support\Facades\Blade;
use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class ComponentClassCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Component Class Exists';
    }

    public function category(): string
    {
        return 'components';
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $aliases = app('blade.compiler')->getClassComponentAliases();
        $locations = [];

        foreach ($aliases as $alias => $class) {
            if (!class_exists($class)) {
                $locations[] = [
                    'alias' => $alias,
                    'class' => $class,
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All registered component classes exist.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' component class(es) not found.',
            locations: $locations,
            suggestion: 'Ensure the component class file exists and is autoloadable.',
        );
    }
}
