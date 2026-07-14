<?php

namespace SajjadHossain\Doctor\Checks\Middleware;

use SajjadHossain\Doctor\Concerns\ResolvesMiddlewareAliases;
use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class UnregisteredMiddlewareCheck implements HealthCheck
{
    use ResolvesMiddlewareAliases;

    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
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

        $registeredAliases = $this->getRegisteredAliases();
        $registeredGroups = $this->getRegisteredGroups();

        $builtinAliases = [
            'auth', 'auth.basic', 'auth.session', 'cache.headers',
            'can', 'guest', 'password.confirm', 'precognitive',
            'signed', 'subscribed', 'throttle', 'verified',
        ];
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
                            'issue' => "Middleware alias '{$alias}' is not registered",
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
            message: count($locations) . ' middleware alias(es) may not be registered.',
            locations: $locations,
            suggestion: 'Register the middleware alias or check the class exists.',
        );
    }

}
