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
                    'suggestion' => 'Use ->value(\'column\') or add a null guard before accessing the property.',
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
        $contextStart = max(0, $offset - 400);
        $context = substr($content, $contextStart, $offset - $contextStart);

        // Inside groupBy/partition closure — each group is guaranteed ≥1 item
        if (
            preg_match('/\b(groupBy|partition)\s*\(/', $context)
            && preg_match('/\b(map|each|filter|transform)\s*\(\s*function\s*\(/', $context)
        ) {
            return true;
        }

        // Inside a @if ($var != 0) / @if ($var > 0) Blade guard
        if (preg_match('/@if\s*\([^)]*(?:!= ?0|!== ?0|> ?0|!\s*=\s*null|->count\s*\(|->isNotEmpty\s*\()/', $context)) {
            return true;
        }

        // Inside any @if block (check @if / @endif balance in preceding lines)
        $before = substr($content, 0, $offset);
        $openCount = preg_match_all('/@if\s*\(/', $before);
        $closeCount = preg_match_all('/@endif/', $before);
        if ($openCount > $closeCount) {
            return true;
        }

        // Inside PHP if guard checking count, isNotEmpty, or != 0
        if (preg_match('/if\s*\([^)]*(?:->count\s*\(\s*\)\s*[!><]|->isNotEmpty\s*\(\s*\)|[!><]=?\s*0\b)/', $context)) {
            return true;
        }

        // Calls chained with nullsafe ?-> after first()
        $afterFirst = substr($content, $offset + strlen('->first()'), 15);
        if (str_contains($afterFirst, '?->')) {
            return true;
        }

        return false;
    }
}
