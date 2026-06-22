<?php

namespace SajjadHossain\Doctor\Checks\Container;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class SingletonAfterFirstResolveCheck implements HealthCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Singleton Registered in boot() After First Resolve';
    }

    public function category(): string
    {
        return 'container';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: [app_path('Providers')];
        $ignore = config('doctor.ignore.container', []);

        foreach ($paths as $path) {
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

                $realPath = $file->getRealPath();
                if ($this->isIgnored($realPath, $ignore)) {
                    continue;
                }

                $content = file_get_contents($realPath);
                $stripped = preg_replace('#/\*.*?\*/#s', '', $content);
                $stripped = preg_replace('!//[^\n]*!', '', $stripped);

                // The real issue this check is meant to surface is a
                // singleton being registered in boot() AFTER some other
                // provider has already resolved the abstract during
                // register(). Detecting that reliably requires runtime
                // container tracing. As a heuristic, we only flag when:
                //   1) boot() calls $this->app->singleton(...) AND
                //   2) the SAME file also calls ->make(...), ->resolve(...),
                //      or another singleton/bind for the same abstract in
                //      register() — which would indicate the abstract might
                //      already have been resolved earlier.
                //
                // A singleton in boot() WITHOUT any same-file early resolve
                // is the standard, documented Laravel pattern and is NOT a
                // problem.
                if (! $this->hasBootSingleton($stripped)) {
                    continue;
                }

                if ($this->hasEarlyResolveInSameFile($stripped)) {
                    $locations[] = [
                        'file' => $realPath,
                        'issue' => 'singleton() registered in boot() but the same file appears to resolve the abstract earlier',
                        'value' => 'Move the singleton binding to register() to avoid double construction.',
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
                message: 'No singletons registered in boot() after first resolve detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' singleton(s) registered in boot() after a possible earlier resolve.',
            locations: $locations,
            suggestion: 'Register the singleton in register() so that it is in place before any provider resolves it.',
        );
    }

    private function hasBootSingleton(string $content): bool
    {
        $body = $this->extractMethodBody($content, 'boot');
        if ($body === null) {
            return false;
        }

        return (bool) preg_match('/\$this->app->singleton\s*\(/', $body);
    }

    /**
     * Detect in the same file a possible earlier resolve of an abstract that
     * gets re-bound as a singleton in boot().
     */
    private function hasEarlyResolveInSameFile(string $content): bool
    {
        $registerBody = $this->extractMethodBody($content, 'register') ?? '';

        // If register() itself calls app()->make(...) or resolve(...) for
        // an abstract, that abstract is already resolved by the time
        // boot() runs.
        if (preg_match('/\$this->app->(make|resolve)\s*\(/', $registerBody)) {
            return true;
        }

        // If register() calls bind() for the same abstract that boot()
        // re-binds as singleton(), the new binding in boot() is too late
        // only if anything resolved the old binding between register()
        // and boot(). We can't detect that statically, so we DON'T flag
        // it (a duplicate bind+singleton for the same key is a different
        // concern, not a "singleton-after-resolve" issue).

        return false;
    }

    private function extractMethodBody(string $content, string $method): ?string
    {
        if (! preg_match('/function\s+'.preg_quote($method, '/').'\s*\([^)]*\)\s*\{(.*?)\n\s*\}/s', $content, $m)) {
            return null;
        }

        return $m[1];
    }

    private function isIgnored(string $path, array $patterns): bool
    {
        $normalized = str_replace('\\', '/', $path);
        foreach ($patterns as $pattern) {
            $normalizedPattern = str_replace('\\', '/', $pattern);
            if (fnmatch($normalizedPattern, $normalized) || str_contains($normalized, $normalizedPattern)) {
                return true;
            }
        }
        return false;
    }
}
