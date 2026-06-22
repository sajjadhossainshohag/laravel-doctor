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
            // A job class implements ShouldQueue (an interface) AND/OR uses
            // the Queueable trait. Because Queueable is a trait,
            // is_subclass_of() never matches it — we must inspect
            // class_uses_recursively() instead.
            $isJob = is_subclass_of($class, 'Illuminate\Contracts\Queue\ShouldQueue');
            if (! $isJob) {
                $traits = $this->classUsesRecursive($class);
                if (in_array('Illuminate\Bus\Queueable', $traits, true)) {
                    // Only treat as a job if the class also has ShouldQueue
                    // in its interface list OR defines dispatch/handle-style
                    // methods. By itself, Queueable is also used by
                    // non-job classes (e.g. queueable notifications).
                    if (is_subclass_of($class, 'Illuminate\Contracts\Queue\ShouldQueue')
                        || $this->classHasMethod($class, 'handle')) {
                        $isJob = true;
                    }
                }
            }

            if (! $isJob) {
                continue;
            }

            try {
                $reflection = new \ReflectionClass($class);
                if ($reflection->isAbstract()) {
                    continue;
                }
                if ($reflection->isTrait()) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }

            // Job may use handle() OR __invoke() OR be invokable via
            // dispatch sync.
            $hasHandle = $this->classHasMethod($class, 'handle');
            $hasInvoke = $this->classHasMethod($class, '__invoke');

            if (! $hasHandle && ! $hasInvoke) {
                $locations[] = [
                    'job' => $class,
                    'issue' => 'Job class has no handle() or __invoke() method',
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All job classes have a dispatch method.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' job class(es) missing a dispatch method.',
            locations: $locations,
            suggestion: 'Add a handle() or __invoke() method to the job class.',
        );
    }

    /**
     * @return array<int, string>
     */
    private function classUsesRecursive(string $class): array
    {
        $traits = [];
        do {
            $traits = array_merge(class_uses($class) ?: [], $traits);
        } while ($class = get_parent_class($class));

        return array_unique($traits);
    }

    private function classHasMethod(string $class, string $method): bool
    {
        try {
            $reflection = new \ReflectionClass($class);
            return $reflection->hasMethod($method);
        } catch (\Throwable) {
            return false;
        }
    }
}