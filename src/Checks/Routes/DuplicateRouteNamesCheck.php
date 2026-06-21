<?php

namespace SajjadHossain\Doctor\Checks\Routes;

use Illuminate\Support\Facades\Route;
use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class DuplicateRouteNamesCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Duplicate Route Names';
    }

    public function category(): string
    {
        return 'routes';
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $routes = Route::getRoutes();
        $names = [];
        $locations = [];

        foreach ($routes as $route) {
            $name = $route->getName();
            if ($name !== null) {
                if (isset($names[$name])) {
                    $locations[] = [
                        'name' => $name,
                        'uri' => $route->uri(),
                        'method' => implode('|', $route->methods()),
                    ];
                }
                $names[$name] = true;
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No duplicate route names found.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' duplicate route name(s) detected.',
            locations: $locations,
            suggestion: 'Each route name must be unique. Use unique names for named routes.',
        );
    }
}
