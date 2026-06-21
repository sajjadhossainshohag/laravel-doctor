<?php

namespace SajjadHossain\Doctor\Checks\Routes;

use Illuminate\Support\Facades\Route;
use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class MissingControllerMethodCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Missing Controller Method';
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
        $locations = [];

        foreach ($routes as $route) {
            $action = $route->getAction('controller');
            if ($action && is_string($action)) {
                $parts = explode('@', $action);
                if (count($parts) === 2) {
                    [$class, $method] = $parts;
                    if (class_exists($class) && !method_exists($class, $method)) {
                        $locations[] = [
                            'controller' => $class,
                            'method' => $method,
                            'uri' => $route->uri(),
                            'name' => $route->getName() ?? '(unnamed)',
                        ];
                    }
                }
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All controller methods exist.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' controller method(s) not found.',
            locations: $locations,
            suggestion: 'Create the missing method or update the route to point to an existing method.',
        );
    }
}
