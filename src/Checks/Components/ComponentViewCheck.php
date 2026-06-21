<?php

namespace SajjadHossain\Doctor\Checks\Components;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\View;
use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class ComponentViewCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Component View Exists';
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
                continue;
            }

            try {
                $reflection = new \ReflectionClass($class);
            } catch (\Throwable) {
                continue;
            }

            if (!$reflection->isSubclassOf('Illuminate\View\Component')) {
                continue;
            }

            try {
                $component = app()->make($class);
            } catch (BindingResolutionException) {
                continue;
            }

            $viewName = method_exists($component, 'render')
                ? $component->render()->name()
                : null;

            if ($viewName && !View::exists($viewName)) {
                $locations[] = [
                    'alias' => $alias,
                    'class' => $class,
                    'view' => $viewName,
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All registered component views exist.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' component view(s) not found.',
            locations: $locations,
        );
    }
}
