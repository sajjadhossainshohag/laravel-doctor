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

        // Job constructor parameters are serialized into the queue payload and
        // rehydrated by the worker. The most reliable problem we can
        // statically detect is a Closure-typed parameter — Closures are
        // never serializable. Other types (PDO, Request, Container, etc.)
        // are technically serializable on most drivers but can still fail
        // at runtime; we intentionally do NOT flag those (would over-report
        // common Laravel patterns like injecting a Repository).

        $scanned = 0;
        foreach ($declared as $class) {
            if (! is_subclass_of($class, 'Illuminate\Contracts\Queue\ShouldQueue')) {
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
                    $scanned++;
                    if ($param->isDefaultValueAvailable()) {
                        continue;
                    }
                    $type = $param->getType();
                    if ($type === null) {
                        continue;
                    }

                    // getName() throws on union/intersection types — check
                    // the shape first and walk the parts.
                    $isClosure = false;
                    if ($type instanceof \ReflectionNamedType) {
                        $isClosure = $this->isClosureType($type->getName());
                    } elseif ($type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType) {
                        foreach ($type->getTypes() as $part) {
                            if ($part instanceof \ReflectionNamedType
                                && $this->isClosureType($part->getName())) {
                                $isClosure = true;
                                break;
                            }
                        }
                    }

                    if ($isClosure) {
                        $locations[] = [
                            'job' => $class,
                            'parameter' => $param->getName(),
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
                message: "No Closure-typed job constructor parameters found across {$scanned} queued job(s) inspected.",
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

    private function isClosureType(string $name): bool
    {
        $name = ltrim($name, '?');

        return $name === 'Closure' || str_ends_with($name, '\\Closure');
    }
}
