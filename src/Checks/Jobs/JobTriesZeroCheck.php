<?php

namespace SajjadHossain\Doctor\Checks\Jobs;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class JobTriesZeroCheck implements HealthCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Job $tries Set to 0';
    }

    public function category(): string
    {
        return 'jobs';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: [app_path('Jobs')];

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

                // Allow typed properties (e.g. `public int $tries = 0;`) as well as
                // untyped ones.
                if (! preg_match('/(?:public|protected|private)\s+(?:\??[\w\\\\|&\[\]]+\s+)?\$tries\s*=\s*0\s*;/', $stripped)) {
                    continue;
                }

                // In Laravel, $tries = 0 means "retry forever" (unlimited
                // attempts) — a documented and valid pattern, but it
                // requires either retryUntil() or backoff() to define
                // when the loop ends. If neither is declared, the job
                // will loop indefinitely and the user likely intended
                // a positive number.
                $hasRetryUntil = (bool) preg_match('/function\s+retryUntil\s*\(/', $stripped);
                $hasBackoff = (bool) preg_match('/(?:public|protected|private)\s+(?:\??[\w\\\\|&\[\]]+\s+)?\$backoff\s*=/', $stripped);

                if ($hasRetryUntil || $hasBackoff) {
                    // $tries = 0 is intentional — no warning.
                    continue;
                }

                $locations[] = [
                    'file' => $file->getRealPath(),
                    'issue' => 'Job $tries is set to 0 with no retryUntil()/backoff() — job will retry forever, which is rarely intended',
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No jobs with $tries = 0 detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' job(s) with $tries = 0 and no retry/backoff cap.',
            locations: $locations,
            suggestion: 'Set $tries to a positive integer (e.g. 3), or add a retryUntil()/backoff() to cap the retries.',
        );
    }
}
