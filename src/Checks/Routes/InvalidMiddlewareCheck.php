<?php

namespace SajjadHossain\Doctor\Checks\Routes;

use Illuminate\Support\Facades\Route;
use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class InvalidMiddlewareCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Invalid Route Middleware';
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
        $locations = [];

        $registeredAliases = $this->getRegisteredAliases();
        $registeredGroups = $this->getRegisteredGroups();

        $builtinAliases = [
            'auth', 'auth.basic', 'auth.session', 'cache.headers',
            'can', 'guest', 'password.confirm', 'precognitive',
            'signed', 'subscribed', 'throttle', 'verified',
        ];
        $builtinGroups = ['web', 'api'];

        foreach ($routes as $route) {
            $middleware = $route->middleware();
            foreach ($middleware as $mw) {
                $name = explode(':', $mw, 2)[0];

                $isValid = in_array($name, $builtinAliases, true)
                    || in_array($name, $registeredAliases, true)
                    || in_array($name, $builtinGroups, true)
                    || in_array($name, $registeredGroups, true)
                    || class_exists($name);

                if (! $isValid) {
                    $locations[] = [
                        'middleware' => $mw,
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
                message: 'All route middleware references are valid.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' middleware reference(s) may be invalid.',
            locations: $locations,
            suggestion: 'Register the middleware alias or check the class exists.',
        );
    }

    private function getRegisteredAliases(): array
    {
        $aliases = [];

        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        if (method_exists($kernel, 'getRouteMiddleware')) {
            $aliases = array_merge($aliases, array_keys($kernel->getRouteMiddleware()));
        }

        $router = app('router');
        if (method_exists($router, 'getMiddleware')) {
            $aliases = array_merge($aliases, array_keys($router->getMiddleware()));
        }

        return array_values(array_unique($aliases));
    }

    private function getRegisteredGroups(): array
    {
        $groups = [];

        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        if (method_exists($kernel, 'getMiddlewareGroups')) {
            $groups = array_merge($groups, array_keys($kernel->getMiddlewareGroups()));
        }

        $router = app('router');
        if (method_exists($router, 'getMiddlewareGroups')) {
            $groups = array_merge($groups, array_keys($router->getMiddlewareGroups()));
        }

        return array_values(array_unique($groups));
    }
}
