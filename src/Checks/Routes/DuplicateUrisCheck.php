<?php

namespace SajjadHossain\Doctor\Checks\Routes;

use Illuminate\Support\Facades\Route;
use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class DuplicateUrisCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Duplicate URIs';
    }

    public function category(): string
    {
        return 'routes';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $routes = Route::getRoutes();
        $map = [];
        $locations = [];

        foreach ($routes as $route) {
            $key = $route->uri() . '|' . implode('|', $route->methods());
            if (isset($map[$key])) {
                $locations[] = [
                    'uri' => $route->uri(),
                    'method' => implode('|', $route->methods()),
                    'name' => $route->getName() ?? '(unnamed)',
                ];
            }
            $map[$key] = true;
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No duplicate URI + method combinations found.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' duplicate URI(s) detected.',
            locations: $locations,
        );
    }
}
