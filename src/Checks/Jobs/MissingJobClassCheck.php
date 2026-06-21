<?php

namespace SajjadHossain\Doctor\Checks\Jobs;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class MissingJobClassCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Missing Job Classes';
    }

    public function category(): string
    {
        return 'jobs';
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $locations = [];

        $jobCalls = $this->findJobDispatchCalls();
        foreach ($jobCalls as $jobClass) {
            if (!class_exists($jobClass)) {
                $locations[] = [
                    'job' => $jobClass,
                    'issue' => 'Job class does not exist',
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All dispatched job classes exist.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' dispatched job class(es) not found.',
            locations: $locations,
            suggestion: 'Create the job class or fix the dispatch call.',
        );
    }

    private function findJobDispatchCalls(): array
    {
        $jobs = [];
        $paths = config('doctor.scan_paths', [app_path(), resource_path('views')]);

        foreach ($paths as $path) {
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

                // Match FQCN dispatch calls:  App\Jobs\SendEmail::dispatch(...)
                // or same-namespace calls:    SendEmail::dispatch(...)
                preg_match_all(
                    '/([\w\\\\]+)(?:::dispatch|\s*::\s*dispatchIf|\s*::\s*dispatchUnless)\s*\(/',
                    $content,
                    $matches
                );

                foreach ($matches[1] as $class) {
                    $class = ltrim($class, '\\');
                    if (in_array($class, ['Bus', 'Queue', 'dispatch', 'event'], true)) {
                        continue;
                    }
                    if (str_contains($class, '\\') || class_exists($class)) {
                        $jobs[] = $class;
                    } else {
                        // Try resolving via `use` statements in this file.
                        $resolved = $this->resolveShortClass($content, $class);
                        if ($resolved) {
                            $jobs[] = $resolved;
                        }
                    }
                }
            }
        }

        return array_values(array_unique($jobs));
    }

    private function resolveShortClass(string $content, string $short): ?string
    {
        if (preg_match_all('/^use\s+([\w\\\\]+)\s*;/m', $content, $uses)) {
            foreach ($uses[1] as $fqcn) {
                $parts = explode('\\', ltrim($fqcn, '\\'));
                if (end($parts) === $short) {
                    return ltrim($fqcn, '\\');
                }
            }
        }

        return null;
    }
}
