<?php

namespace SajjadHossain\Doctor\Checks\Components;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class ComponentNamespaceCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Component Namespace Mappings';
    }

    public function category(): string
    {
        return 'components';
    }

    public function severity(): Severity
    {
        return Severity::Info;
    }

    public function run(): CheckResult
    {
        $finder = app('view')->getFinder();
        $hints = $finder->getHints();
        $locations = [];

        foreach ($hints as $namespace => $paths) {
            foreach ($paths as $path) {
                if (!is_dir($path)) {
                    $locations[] = [
                        'namespace' => $namespace,
                        'path' => $path,
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
                message: 'All component namespace directory mappings exist.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' namespace mapped director(ies) not found.',
            locations: $locations,
        );
    }
}
