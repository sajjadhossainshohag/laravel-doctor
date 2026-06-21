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

        // Laravel 11+ configures middleware in bootstrap/app.php using
        // ->withMiddleware(fn ($mw) => $mw->alias(...)->appendToGroup(...)).
        // Older Laravel versions use Http\Kernel::$middlewareAliases / $middlewareGroups.
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
                // ignore — empty registry
            }
        }

        // Also scan service providers for ->aliasMiddleware() calls (old-style)
        $registeredAliases = array_merge($registeredAliases, $this->scanProvidersForMiddlewareAliases());

        // Built-in middleware always available in Laravel
        $builtin = ['web', 'api', 'auth', 'guest'];

        foreach ($routes as $route) {
            $middleware = $route->middleware();
            foreach ($middleware as $mw) {
                $name = explode(':', $mw, 2)[0];

                $isValid = in_array($name, $builtin, true)
                    || in_array($name, $registeredAliases, true)
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

        // ->alias([ 'key' => Class::class, ... ]) — array form
        if (preg_match_all('/->alias\s*\(\s*\[(.*?)\]\s*\)/s', $content, $m2)) {
            foreach ($m2[1] as $block) {
                if (preg_match_all('/[\'"]([a-z0-9_.-]+)[\'"]\s*=>/i', $block, $m3)) {
                    $aliases = array_merge($aliases, $m3[1]);
                }
            }
        }

        // ->aliases([ 'key' => Class::class, ... ]) — older plural form
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

                // ->aliasMiddleware('name', Class::class)
                if (preg_match_all('/->aliasMiddleware\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,/', $content, $m)) {
                    $aliases = array_merge($aliases, $m[1]);
                }

                // ->alias('name', Class::class) outside bootstrap/app.php
                if (preg_match_all('/->alias\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,/', $content, $m2)) {
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
