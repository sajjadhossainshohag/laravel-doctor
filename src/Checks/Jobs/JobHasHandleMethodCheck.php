<?php

namespace SajjadHossain\Doctor\Checks\Jobs;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class JobHasHandleMethodCheck implements HealthCheck
{
    private array $jobClasses = [];

    public function withPaths(array $paths): static
    {
        $this->jobClasses = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Job handle() Method';
    }

    public function category(): string
    {
        return 'jobs';
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $declared = $this->jobClasses ?: get_declared_classes();

        foreach ($declared as $class) {
            if (!is_subclass_of($class, 'Illuminate\Contracts\Queue\ShouldQueue') &&
                !is_subclass_of($class, 'Illuminate\Bus\Queueable')) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            if ($reflection->isAbstract()) {
                continue;
            }

            if (!$reflection->hasMethod('handle')) {
                $locations[] = [
                    'job' => $class,
                    'issue' => 'Job class has no handle() method',
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All job classes have a handle() method.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' job class(es) missing handle() method.',
            locations: $locations,
            suggestion: 'Add a handle() method to the job class.',
        );
    }
}
