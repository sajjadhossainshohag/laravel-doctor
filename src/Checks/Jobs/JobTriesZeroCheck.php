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
                // attempts). This is intentional ONLY when the user
                // provides an EXPLICIT CAP somewhere:
                //
                //   - retryUntil(): a method that returns a DateTime /
                //                   timestamp after which retries stop.
                //   - tries():      a method that returns an int retry
                //                   cap (overrides the property).
                //
                // $backoff is the DELAY between retries — it does NOT
                // cap retry count. A job with `$tries = 0; $backoff = 30;`
                // retries forever with 30-second gaps. It must still be
                // flagged.
                $hasRetryUntil = (bool) preg_match('/function\s+retryUntil\s*\(/', $stripped);
                $hasTriesMethod = (bool) preg_match('/function\s+tries\s*\(\s*\)/', $stripped);

                // $backoff detection is intentionally kept ONLY for
                // diagnostic purposes — it does NOT short-circuit the
                // warning. We surface it in the issue message so the
                // developer understands that the property is present but
                // unrelated to retry count.
                $hasBackoffProperty = (bool) preg_match(
                    '/(?:public|protected|private)\s+(?:\??[\w\\\\|&\[\]]+\s+)?\$backoff\s*=/',
                    $stripped
                );
                $hasBackoffMethod = (bool) preg_match('/function\s+backoff\s*\(\s*\)/', $stripped);
                $hasBackoff = $hasBackoffProperty || $hasBackoffMethod;

                if ($hasRetryUntil || $hasTriesMethod) {
                    // $tries = 0 is intentional — no warning.
                    continue;
                }

                $message = 'Job $tries is set to 0 with no retryUntil()/tries() — job will retry forever, which is rarely intended';
                if ($hasBackoff) {
                    $message .= ' (note: $backoff/backoff() is the delay between retries, not a retry cap — it does NOT stop the infinite loop)';
                }

                $locations[] = [
                    'file' => $file->getRealPath(),
                    'issue' => $message,
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
            message: count($locations).' job(s) with $tries = 0 and no retry cap.',
            locations: $locations,
            suggestion: 'Set $tries to a positive integer (e.g. 3), or add retryUntil()/tries() to cap the retries. $backoff is the delay between retries and does NOT cap retries.',
        );
    }
}