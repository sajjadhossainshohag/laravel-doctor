<?php

namespace SajjadHossain\Doctor\Checks\Events;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class ListenerMissingHandleMethodCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Listener Missing handle() Method';
    }

    public function category(): string
    {
        return 'events';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = [app_path('Listeners')];

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
                $stripped = preg_replace('#/\*.*?\*/#s', '', $content);
                $stripped = preg_replace('!//[^\n]*!', '', $stripped);

                if (! preg_match('/class\s+(\w+)/', $stripped, $m)) {
                    continue;
                }
                $className = $m[1];

                // A listener only needs handle() if it doesn't use one of
                // Laravel's other listener dispatch mechanisms:
                //   - subscribe() method → it is an event subscriber
                //     (Laravel routes by the events returned by subscribe())
                //   - __invoke() method  → invokable listener
                //   - handle() method   → standard listener
                $hasHandle = (bool) preg_match('/function\s+handle\s*\(/', $stripped);
                $hasInvoke = (bool) preg_match('/function\s+__invoke\s*\(/', $stripped);
                $hasSubscribe = (bool) preg_match('/function\s+subscribe\s*\(/', $stripped);

                if ($hasHandle || $hasInvoke || $hasSubscribe) {
                    continue;
                }

                $locations[] = [
                    'file' => $file->getRealPath(),
                    'issue' => "Listener '{$className}' has no handle(), __invoke(), or subscribe() method",
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All listener classes define a dispatch method.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' listener(s) missing a dispatch method.',
            locations: $locations,
            suggestion: 'Add a handle(), __invoke(), or subscribe() method to the listener class.',
        );
    }
}
