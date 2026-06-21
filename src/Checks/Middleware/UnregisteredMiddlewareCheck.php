<?php

namespace SajjadHossain\Doctor\Checks\Middleware;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class UnregisteredMiddlewareCheck implements HealthCheck
{
    private array $scanPaths = [];
    private ?string $bootstrapPath = null;

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function withBootstrapPath(string $path): static
    {
        $this->bootstrapPath = $path;
        return $this;
    }

    public function name(): string
    {
        return 'Middleware Not Registered';
    }

    public function category(): string
    {
        return 'middleware';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $appPath = $this->bootstrapPath ?? base_path('bootstrap/app.php');

        if (! file_exists($appPath)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'bootstrap/app.php not found — cannot check middleware registration.',
            );
        }

        $appContent = file_get_contents($appPath);
        $registeredAliases = $this->extractMiddlewareAliases($appContent);
        $registeredGroups = $this->extractMiddlewareGroups($appContent);

        foreach ($this->scanPaths ?: config('doctor.scan_paths', [app_path()]) as $path) {
            if (! is_dir($path)) {
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
                if (preg_match('/->middleware\s*\(\s*[\'"]([a-z0-9_.-]+)[\'"]/', $content, $m)) {
                    $alias = $m[1];
                    $base = explode(':', $alias, 2)[0];

                    // Built-in middleware group names are always available.
                    $builtin = ['web', 'api', 'auth', 'guest'];

                    $known = in_array($base, $builtin, true)
                        || in_array($alias, $registeredAliases, true)
                        || in_array($base, $registeredGroups, true)
                        || class_exists($base);

                    if (! $known) {
                        $locations[] = [
                            'file' => $file->getRealPath(),
                            'issue' => "Middleware alias '{$alias}' is not registered in bootstrap/app.php",
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
                message: 'No obviously unregistered middleware aliases detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' middleware alias(es) may not be registered.',
            locations: $locations,
            suggestion: 'Register the middleware alias in bootstrap/app.php using ->withMiddleware().',
        );
    }

    /**
     * Parse bootstrap/app.php to extract middleware aliases registered via
     * ->alias('foo', Bar::class) or ->alias(['foo' => Bar::class]).
     *
     * @return array<int, string>
     */
    private function extractMiddlewareAliases(string $content): array
    {
        $aliases = [];

        // Form 1: ->alias('foo', Bar::class)
        if (preg_match_all('/->alias\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,/', $content, $m)) {
            $aliases = array_merge($aliases, $m[1]);
        }

        // Form 2: ->aliases(['foo' => Bar::class, ...])
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
     * Parse bootstrap/app.php to extract middleware group names registered via
     * ->appendToGroup('group', ...) / ->prependToGroup('group', ...) / $middleware->web(...)
     * or ->group('group', [...]).
     *
     * @return array<int, string>
     */
    private function extractMiddlewareGroups(string $content): array
    {
        $groups = [];

        // Forms:
        //   ->appendToGroup('web', ...) / ->prependToGroup('web', ...)
        if (preg_match_all('/->(?:appendToGroup|prependToGroup)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
            $groups = array_merge($groups, $m[1]);
        }

        //   ->group('web', [...]) / $middleware->web([...])
        if (preg_match_all('/->group\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,/', $content, $m2)) {
            $groups = array_merge($groups, $m2[1]);
        }

        if (preg_match_all('/\$middleware->([a-z][a-z0-9_]*)\s*\(/', $content, $m3)) {
            $groups = array_merge($groups, $m3[1]);
        }

        return array_values(array_unique($groups));
    }
}
