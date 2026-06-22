<?php

namespace SajjadHossain\Doctor\Checks\Jobs;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class JobDependencyResolutionCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Job Constructor Unserializable Parameter';
    }

    public function category(): string
    {
        return 'jobs';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $declared = get_declared_classes();

        // Job constructor parameters are serialized into the queue payload
        // and rehydrated by the worker. They are NOT container-resolved at
        // construction time, so trying to `app()->make()` them is wrong.
        // Instead, we verify each constructor parameter has a type that
        // can be safely serialized (scalar / array / object that exists
        // at runtime) and is not a Closure / resource.

        foreach ($declared as $class) {
            if (!is_subclass_of($class, 'Illuminate\Contracts\Queue\ShouldQueue')) {
                continue;
            }

            try {
                $reflection = new \ReflectionClass($class);
                if ($reflection->isAbstract()) {
                    continue;
                }

                $constructor = $reflection->getConstructor();
                if ($constructor === null) {
                    continue;
                }

                foreach ($constructor->getParameters() as $param) {
                    if ($param->isDefaultValueAvailable()) {
                        continue;
                    }
                    $type = $param->getType();
                    if (! $type) {
                        continue;
                    }

                    $typeName = $type->getName();
                    // Closure and resource are inherently unserializable.
                    if ($typeName === 'Closure') {
                        $locations[] = [
                            'job' => $class,
                            'parameter' => $param->getName(),
                            'type' => $typeName,
                            'issue' => 'Job constructor parameter is typed as Closure — Closures cannot be serialized into the queue payload',
                        ];
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All job constructor parameters are serializable.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' job constructor parameter(s) cannot be serialized into a queue payload.',
            locations: $locations,
            suggestion: 'Use serializable types in job constructor parameters (scalars, arrays, model ids).',
        );
    }
}
