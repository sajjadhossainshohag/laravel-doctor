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
            // Include domain and scheme in the key so routes that are
            // distinct on a subdomain (e.g. /api on the main app vs
            // /api on the admin app) aren't flagged as duplicates.
            $methods = $route->methods();
            if (in_array('GET', $methods, true) && in_array('HEAD', $methods, true)) {
                $methods = array_values(array_diff($methods, ['HEAD']));
            }
            sort($methods);
            $key = implode('|', $methods)
                . '|' . ($route->getDomain() ?? '')
                . '|' . $route->uri();

            if (isset($map[$key])) {
                $locations[] = [
                    'uri' => $route->uri(),
                    'method' => implode('|', $route->methods()),
                    'domain' => $route->getDomain() ?? '',
                    'name' => $route->getName() ?? '(unnamed)',
                    'issue' => 'Route is shadowed by an earlier route with the same URI + method + domain — the earlier route will be matched first',
                ];
            } else {
                $map[$key] = true;
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No duplicate URI + method + domain combinations found.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' duplicate route(s) detected.',
            locations: $locations,
            suggestion: 'Combine the duplicate routes, give one of them a different URI/method, or remove the duplicate definition.',
        );
    }
}
