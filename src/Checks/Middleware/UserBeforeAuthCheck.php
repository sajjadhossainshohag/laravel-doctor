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

                if (! preg_match('/\$request->user\s*\(/', $stripped)) {
                    continue;
                }
                if (! preg_match('/function\s+handle\s*\([^)]*\)\s*\{/', $stripped)) {
                    continue;
                }

                // $request->user() is documented to return null when the
                // request is unauthenticated, and Laravel intentionally
                // supports reading it in middleware that runs both before
                // and after the auth stage (guest detection, attaching the
                // user to logs, etc.). Flag only as an informational
                // reminder that the value may be null, never as a hard
                // error.
                $handleBody = $this->extractHandleBody($stripped);
                if ($this->hasNullGuardAroundUserCall($handleBody)) {
                    continue;
                }

                $locations[] = [
                    'file' => $file->getRealPath(),
                    'issue' => '$request->user() called in handle() without null guard — may return null if auth has not run',
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
            passed: true,
            message: count($locations).' middleware(s) read $request->user() without a null guard. This is informational — reading the user before auth is supported, but the value is nullable.',
            locations: $locations,
            suggestion: 'If the code dereferences $user, guard with if ($user) / if ($user !== null) or use nullsafe $user?->...',
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
            '/\$(?:user|\w+)\s*=\s*\$request->user\s*\(.*?;\s*(?:if\s*\(|return\s+|\?->|null\??\s*[{,=])|if\s*\(\s*!\s*\$(?:user|\w+)\s*\)|if\s*\(\s*\$(?:user|\w+)\s*===\s*null\s*\)|if\s*\(\s*null\s*===\s*\$(?:user|\w+)\s*\)|is_null\s*\(\s*\$(?:user|\w+)\s*\)|->user\s*\(\s*\)\?->/s',
            $body
        );
    }
}