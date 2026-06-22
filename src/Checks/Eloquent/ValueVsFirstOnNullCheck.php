<?php

namespace SajjadHossain\Doctor\Checks\Eloquent;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class ValueVsFirstOnNullCheck implements HealthCheck
{
    public function name(): string
    {
        return '->first()->column on Null Result';
    }

    public function category(): string
    {
        return 'eloquent';
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = config('doctor.scan_paths', [app_path(), resource_path('views')]);

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
                if (! preg_match('/->first\s*\(\)\s*->\w+/', $content)) {
                    continue;
                }

                if ($this->allCallsAreGuarded($content)) {
                    continue;
                }

                $locations[] = [
                    'file' => $file->getRealPath(),
                    'issue' => '->first()->property called without null check',
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No unsafe ->first()->property chains detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' unsafe ->first()->property chain(s) detected.',
            locations: $locations,
            suggestion: 'Use ->value(\'column\') or optional($result) to avoid null dereference.',
        );
    }

    private function allCallsAreGuarded(string $content): bool
    {
        preg_match_all('/->first\s*\(\)\s*->\w+/', $content, $matches, PREG_OFFSET_CAPTURE);

        if (empty($matches[0])) {
            return true;
        }

        foreach ($matches[0] as [$match, $offset]) {
            if (! $this->callIsGuarded($content, $offset)) {
                return false;
            }
        }

        return true;
    }

    private function callIsGuarded(string $content, int $offset): bool
    {
        // Look at the 400 chars BEFORE the ->first() call. This is a much
        // tighter guard than the old logic, which used a coarse "any open
        // @if" that suppressed unrelated blocks.
        $contextStart = max(0, $offset - 400);
        $context = substr($content, $contextStart, $offset - $contextStart);

        // Inside groupBy/partition closure — each group is guaranteed ≥1 item.
        if (
            preg_match('/\b(groupBy|partition)\s*\(/', $context)
            && preg_match('/\b(map|each|filter|transform)\s*\(\s*function\s*\(/', $context)
        ) {
            return true;
        }

        // Blade @if with a count/isNotEmpty/comparison guard that closes
        // BEFORE the ->first() call (so the @if actually guards it).
        if (preg_match('/@if\s*\([^)]*(?:->count\s*\(\s*\)\s*[!><]|->isNotEmpty\s*\(\s*\)|[!><]=?\s*0\b|!==\s*null|!=\s*null)\s*\)[^@]*@endif/s', $context)) {
            return true;
        }

        // PHP `if (...count/isNotEmpty/!==null) {` opening that hasn't been
        // closed before the ->first() call.
        $openIf = preg_match_all('/\bif\s*\(/', $context);
        $closeBrace = preg_match_all('/\}\s*/', $context);
        if ($openIf > $closeBrace && preg_match('/if\s*\([^)]*(?:->count\s*\(\s*\)\s*[!><]|->isNotEmpty\s*\(\s*\)|!?==?\s*null)\s*\)/s', $context)) {
            return true;
        }

        // Calls chained with nullsafe ?-> after first().
        $afterFirst = substr($content, $offset + strlen('->first()'), 15);
        if (str_contains($afterFirst, '?->')) {
            return true;
        }

        return false;
    }
}
