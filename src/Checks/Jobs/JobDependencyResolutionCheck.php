<?php

namespace SajjadHossain\Doctor\Checks\Jobs;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class JobDependencyResolutionCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Job Dependency Resolution';
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
                    $type = $param->getType();
                    if ($type && !$type->isBuiltin()) {
                        $typeName = $type->getName();
                        try {
                            app()->make($typeName);
                        } catch (\Throwable $e) {
                            $locations[] = [
                                'job' => $class,
                                'parameter' => $param->getName(),
                                'type' => $typeName,
                                'issue' => 'Cannot resolve dependency: ' . $e->getMessage(),
                            ];
                        }
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
                message: 'All job constructor dependencies are resolvable.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' job dependency(ies) not resolvable.',
            locations: $locations,
        );
    }
}
