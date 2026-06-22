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

            $viewName = null;

            if (method_exists($component, 'render')) {
                try {
                    $rendered = $component->render();
                } catch (\Throwable) {
                    $rendered = null;
                }

                if ($rendered instanceof \Illuminate\Contracts\View\View) {
                    $viewName = $rendered->name();
                } elseif (is_string($rendered) && $rendered !== '') {
                    // AnonymousComponent::render() returns the view name as
                    // a string. Same for inline components.
                    $viewName = $rendered;
                } elseif ($rendered instanceof \Closure) {
                    // DynamicComponent::render() returns a Closure that
                    // produces a Blade template string. We can't extract a
                    // single view name from that without invoking it, so
                    // skip — it doesn't correspond to one canonical view.
                    $viewName = null;
                }
            }

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
