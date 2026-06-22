<?php

namespace SajjadHossain\Doctor\Checks\Views;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class StackPushMismatchCheck implements HealthCheck
{
    public function name(): string
    {
        return '@stack / @push Mismatches';
    }

    public function category(): string
    {
        return 'views';
    }

    public function severity(): Severity
    {
        return Severity::Info;
    }

    public function run(): CheckResult
    {
        $locations = [];

        $paths = config('view.paths', [resource_path('views')]);

        $allStacks = [];
        $allPushes = [];

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
                $stripped = $this->stripComments($content);

                preg_match_all('/@stack\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $stripped, $stackMatches);
                foreach ($stackMatches[1] as $stackName) {
                    $allStacks[$stackName] = true;
                }

                preg_match_all('/@push\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $stripped, $pushMatches);
                foreach ($pushMatches[1] as $pushName) {
                    $allPushes[$pushName] = ($allPushes[$pushName] ?? 0) + 1;
                }
            }
        }

        foreach ($allPushes as $pushName => $count) {
            if (!isset($allStacks[$pushName])) {
                $locations[] = [
                    'stack' => $pushName,
                    'pushes' => $count,
                    'issue' => '@push targets a stack name with no matching @stack in any scanned view',
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All @push targets have matching @stack definitions.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: true,
            message: count($locations) . ' @push target(s) do not have a matching @stack in any scanned view. This is informational — the @stack may live in a layout rendered outside these directories.',
            locations: $locations,
        );
    }

    private function stripComments(string $content): string
    {
        $content = preg_replace('/\{\{--.*?--\}\}/s', '', $content);
        $content = preg_replace('#/\*.*?\*/#s', '', $content);
        $content = preg_replace('!//[^\n]*!', '', $content);

        return $content;
    }
}