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
        $registeredAliases = [];
        $registeredGroups = [];
        $locations = [];

        $bootstrap = base_path('bootstrap/app.php');
        if (file_exists($bootstrap)) {
            $content = file_get_contents($bootstrap);
            $registeredAliases = $this->parseAliases($content);
            $registeredGroups = $this->parseGroups($content);
        } else {
            try {
                $kernel = app('Illuminate\Contracts\Http\Kernel');
                if (method_exists($kernel, 'getRouteMiddleware')) {
                    $registeredAliases = array_keys($kernel->getRouteMiddleware());
                }
                if (method_exists($kernel, 'getMiddlewareGroups')) {
                    $registeredGroups = array_keys($kernel->getMiddlewareGroups());
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        $registeredAliases = array_merge($registeredAliases, $this->scanProvidersForMiddlewareAliases());

        // Full Laravel 10/11+ built-in alias list — these are always valid
        // even when the user has not registered them.
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
            suggestion: 'Register the middleware alias in bootstrap/app.php or check the class exists.',
        );
    }

    /**
     * @return array<int, string>
     */
    private function parseAliases(string $content): array
    {
        $aliases = [];

        if (preg_match_all('/->alias\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,/', $content, $m)) {
            $aliases = array_merge($aliases, $m[1]);
        }

        if (preg_match_all('/->alias\s*\(\s*\[(.*?)\]\s*\)/s', $content, $m2)) {
            foreach ($m2[1] as $block) {
                if (preg_match_all('/[\'"]([a-z0-9_.-]+)[\'"]\s*=>/i', $block, $m3)) {
                    $aliases = array_merge($aliases, $m3[1]);
                }
            }
        }

        if (preg_match_all('/->aliases\s*\(\s*\[(.*?)\]\s*\)/s', $content, $m2)) {
            foreach ($m2[1] as $block) {
                if (preg_match_all('/[\'"]([a-z0-9_.-]+)[\'"]\s*=>/i', $block, $m3)) {
                    $aliases = array_merge($aliases, $m3[1]);
                }
            }
        }

        return array_values(array_unique($aliases));
    }

    /**
     * @return array<int, string>
     */
    private function scanProvidersForMiddlewareAliases(): array
    {
        $aliases = [];
        $providerPaths = [
            app_path('Providers'),
            base_path('modules'),
        ];

        foreach ($providerPaths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $content = file_get_contents($file->getRealPath());
                $stripped = preg_replace('#/\*.*?\*/#s', '', $content);
                $stripped = preg_replace('!//[^\n]*!', '', $stripped);

                if (preg_match_all('/->aliasMiddleware\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,/', $stripped, $m)) {
                    $aliases = array_merge($aliases, $m[1]);
                }

                if (preg_match_all('/->alias\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,/', $stripped, $m2)) {
                    $aliases = array_merge($aliases, $m2[1]);
                }
            }
        }

        return array_values(array_unique($aliases));
    }

    /**
     * @return array<int, string>
     */
    private function parseGroups(string $content): array
    {
        $groups = [];

        if (preg_match_all('/->(?:appendToGroup|prependToGroup)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
            $groups = array_merge($groups, $m[1]);
        }

        if (preg_match_all('/->group\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,/', $content, $m2)) {
            $groups = array_merge($groups, $m2[1]);
        }

        if (preg_match_all('/\$middleware->([a-z][a-z0-9_]*)\s*\(/', $content, $m3)) {
            $groups = array_merge($groups, $m3[1]);
        }

        return array_values(array_unique($groups));
    }
}
