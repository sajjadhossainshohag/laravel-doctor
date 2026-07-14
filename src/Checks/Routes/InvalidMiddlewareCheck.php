<?php

namespace SajjadHossain\Doctor\Checks\Routes;

use Illuminate\Support\Facades\Route;
use SajjadHossain\Doctor\Concerns\ResolvesMiddlewareAliases;
use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class InvalidMiddlewareCheck implements HealthCheck
{
    use ResolvesMiddlewareAliases;

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

}
