<?php

namespace SajjadHossain\Doctor\Checks\Middleware;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class UserBeforeAuthCheck implements HealthCheck
{
    public function name(): string
    {
        return '$request->user() Called Before Auth';
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

                if (! preg_match('/\$request->user\s*\(/', $content)) {
                    continue;
                }

                if (! preg_match('/function\s+handle\s*\([^)]*\)\s*\{/', $content)) {
                    continue;
                }

                $handleBody = $this->extractHandleBody($content);
                if ($this->hasNullGuardAroundUserCall($handleBody)) {
                    continue;
                }

                $locations[] = [
                    'file' => $file->getRealPath(),
                    'issue' => '$request->user() called in handle() without null guard — may return null if auth hasn\'t run',
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No risky $request->user() calls in middleware.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' middleware(s) call $request->user() without null guard.',
            locations: $locations,
            suggestion: 'Guard with if (!$user) / if ($user === null) or ensure auth middleware runs first.',
        );
    }

    private function extractHandleBody(string $content): string
    {
        if (preg_match('/function\s+handle\s*\([^)]*\)\s*\{(.*)\}/s', $content, $m)) {
            return $m[1];
        }

        return '';
    }

    private function hasNullGuardAroundUserCall(string $body): bool
    {
        return (bool) preg_match(
            '/\$(?:user|\w+)\s*=\s*\$request->user\s*\(.*?;\s*(?:if\s*\(|return\s+|\?->|null\??\s*[{,=])|if\s*\(\s*!\s*\$(?:user|\w+)\s*\)|if\s*\(\s*\$(?:user|\w+)\s*===\s*null\s*\)|if\s*\(\s*null\s*===\s*\$(?:user|\w+)\s*\)|is_null\s*\(\s*\$(?:user|\w+)\s*\)/s',
            $body
        );
    }
}
