<?php

namespace SajjadHossain\Doctor\Checks\Routes;

use Illuminate\Support\Facades\Route;
use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class MissingControllerCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Missing Controller Class';
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
                [$class] = explode('@', $action);
                if (!class_exists($class)) {
                    $locations[] = [
                        'controller' => $class,
                        'uri' => $route->uri(),
                        'name' => $route->getName() ?? '(unnamed)',
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
                message: 'All controller classes exist.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' controller class(es) not found.',
            locations: $locations,
            suggestion: 'Ensure the referenced controller class exists and is autoloadable.',
        );
    }
}
