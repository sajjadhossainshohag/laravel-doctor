<?php

namespace SajjadHossain\Doctor\Checks\Middleware;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class TerminateMethodThrowsCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Middleware terminate() May Throw';
    }

    public function category(): string
    {
        return 'middleware';
    }

    public function severity(): Severity
    {
        return Severity::Info;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = [app_path('Http/Middleware')];

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

                if (! preg_match('/function\s+terminate\s*\([^)]*\)\s*\{(.*?)\n\s*\}/s', $stripped, $m)) {
                    continue;
                }

                $body = $m[1];
                $hasExternalCall = (bool) preg_match(
                    '/\b(DB::|Http::|Log::|Storage::|Mail::|Redis::|Cache::|event\s*\(|dispatch\s*\()/',
                    $body
                );
                $hasTryCatch = (bool) preg_match('/\btry\s*\{/', $body);

                // The previous version of this check claimed exceptions in
                // terminate() are silently swallowed by the kernel. That is
                // incorrect — the kernel calls $instance->terminate() without
                // a try/catch (Illuminate\Foundation\Http\Kernel::terminateMiddleware).
                // The response is already sent to the client by the time
                // terminate() runs, so any exception can disrupt any
                // post-send cleanup work and may surface in the SAPI log.
                // We surface this as informational rather than as a failure,
                // and recommend wrapping external calls in try/catch as a
                // best practice.
                if ($hasExternalCall && ! $hasTryCatch) {
                    $locations[] = [
                        'file' => $file->getRealPath(),
                        'issue' => 'terminate() makes external calls (DB/HTTP/Log/etc.) without try/catch — wrap them to keep cleanup work resilient after the response is sent',
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
                message: 'No risky terminate() methods detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: true,
            message: count($locations).' terminate() method(s) make external calls without try/catch. This is informational — the kernel does not silently swallow terminate exceptions, but cleanup work may still be disrupted.',
            locations: $locations,
            suggestion: 'Wrap terminate() logic in try/catch to keep cleanup work resilient after the response is sent.',
        );
    }
}
