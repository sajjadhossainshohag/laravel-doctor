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
                if (preg_match('/function\s+terminate\s*\([^)]*\)\s*\{(.*?)\n\s*\}/s', $content, $m)) {
                    $body = $m[1];

                    // Only flag when terminate() makes external calls (DB/HTTP/Log/Storage)
                    // WITHOUT wrapping them in try/catch. The response has already been
                    // sent to the client at this point, so exceptions are silently dropped
                    // and harder to debug. Simple variable/log calls without throwables are
                    // fine and shouldn't be flagged.
                    $hasExternalCall = preg_match(
                        '/\b(DB::|Http::|Log::|Storage::|Mail::|Redis::|Cache::|event\s*\(|dispatch\s*\()/',
                        $body
                    );
                    $hasTryCatch = preg_match('/\btry\s*\{/', $body);

                    if ($hasExternalCall && ! $hasTryCatch) {
                        $locations[] = [
                            'file' => $file->getRealPath(),
                            'issue' => 'terminate() makes external calls (DB/HTTP/Log/etc.) without try/catch — exceptions are silently swallowed after response is sent',
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
                message: 'No risky terminate() methods detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' terminate() method(s) may throw silently.',
            locations: $locations,
            suggestion: 'Wrap terminate() logic in try/catch to prevent silent failures.',
        );
    }
}
