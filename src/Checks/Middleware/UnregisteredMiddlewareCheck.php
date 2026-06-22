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

        // Built-in middleware aliases always available in Laravel 10+/11+,
        // whether or not the user has registered them explicitly.
        $builtinAliases = [
            'auth', 'auth.basic', 'auth.session', 'cache.headers',
            'can', 'guest', 'password.confirm', 'precognitive',
            'signed', 'subscribed', 'throttle', 'verified',
        ];
        // Built-in middleware group names.
        $builtinGroups = ['web', 'api'];

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
                $stripped = preg_replace('#/\*.*?\*/#s', '', $content);
                $stripped = preg_replace('!//[^\n]*!', '', $stripped);

                // Match both single-string form (->middleware('alias')) AND array form
                // (->middleware(['auth', 'custom'])). We match against the
                // UN-stripped content so quoted aliases inside ->middleware(...)
                // are still found. Commented-out calls won't match anyway because
                // we strip // and /* */ comments above.
                $aliases = [];
                if (preg_match_all('/->middleware\s*\(\s*[\'"]([a-z0-9_.-]+)[\'"]/', $stripped, $m)) {
                    foreach ($m[1] as $alias) {
                        $aliases[] = $alias;
                    }
                }
                if (preg_match_all('/->middleware\s*\(\s*\[(.*?)\]\s*\)/s', $stripped, $arrM)) {
                    foreach ($arrM[1] as $block) {
                        if (preg_match_all('/[\'"]([a-z0-9_.-]+)[\'"]/i', $block, $innerM)) {
                            foreach ($innerM[1] as $alias) {
                                $aliases[] = $alias;
                            }
                        }
                    }
                }

                foreach ($aliases as $alias) {
                    $base = explode(':', $alias, 2)[0];

                    $known = in_array($base, $builtinAliases, true)
                        || in_array($alias, $registeredAliases, true)
                        || in_array($base, $builtinGroups, true)
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
     * Parse bootstrap/app.php to extract middleware group names.
     *
     * @return array<int, string>
     */
    private function extractMiddlewareGroups(string $content): array
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