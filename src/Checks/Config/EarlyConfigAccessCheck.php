<?php

namespace SajjadHossain\Doctor\Checks\Config;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class EarlyConfigAccessCheck implements HealthCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'env() Called in register() Method';
    }

    public function category(): string
    {
        return 'config';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: [app_path('Providers')];

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

                $content = file_get_contents($file->getRealPath());

                // Extract just the register() method body (greedy match across newlines)
                // so we don't false-positive on env() calls in boot() or other
                // methods. Allow an optional return type between ) and { so
                // modern `public function register(): void { ... }` stubs are
                // not silently skipped.
                if (! preg_match('/function\s+register\s*\([^)]*\)\s*(?::\s*[\\\\\w|&\[\]<>,\s]+)?\s*\{(.*?)\n\s*\}/s', $content, $m)) {
                    continue;
                }

                $registerBody = $m[1];

                // The real Laravel early-access problem is `env()` inside
                // register(). The `config()` repository is fully populated
                // before service providers' register() runs (LoadConfiguration
                // happens before RegisterProviders), so `config()` is safe.
                // `env()` is also fully loaded by then, BUT the documented
                // best practice is to read env() only inside config files or
                // outside providers, because other config files that use
                // `env()` as a default may not have been processed yet.
                //
                // Flag only env() calls inside the register() body, not
                // $env (variable), ->env (chain), 'env(' (string), //env (comment).
                if (preg_match('/(?<![$>\/\\\\])\benv\s*\(/', $registerBody)) {
                    $locations[] = [
                        'file' => $file->getRealPath(),
                        'issue' => 'env() called in register() method — values are loaded but registering providers depending on env at this stage is fragile',
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
                message: 'No env() called inside service provider register() methods.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' service provider(s) call env() in register().',
            locations: $locations,
            suggestion: 'Move env() calls to boot() or to a config file so provider order does not affect their values.',
        );
    }
}
