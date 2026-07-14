<?php

namespace SajjadHossain\Doctor\Output;

use Illuminate\Console\OutputStyle;
use SajjadHossain\Doctor\DTOs\CheckResult;

use function Laravel\Prompts\note;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\error;
use function Laravel\Prompts\table;

class ConsoleRenderer
{
    public function render(OutputStyle $output, array $results, float $duration = 0, bool $verbose = false): void
    {
        $passed = 0;
        $failed = 0;
        $rows = [];
        $failedByCategory = [];

        foreach ($results as $result) {
            $icon = $result->passed ? '✓' : '✗';
            $severity = $result->passed ? '' : strtoupper($result->severity->value);

            $rows[] = [
                $icon,
                $result->category,
                $result->check,
                $severity,
            ];

            if ($result->passed) {
                $passed++;
            } else {
                $failed++;
                $failedByCategory[$result->category][] = $result;
            }
        }

        info('Laravel Doctor — Code Health Scan');
        table(['', 'Category', 'Check', 'Severity'], $rows);

        if (! empty($failedByCategory)) {
            $this->renderIssuesTable($failedByCategory, $verbose);
        }

        $durationText = $duration > 0 ? number_format($duration / 1000, 2) . 's' : '';

        if ($failed === 0) {
            info("All {$passed} checks passed." . ($durationText ? " ({$durationText})" : ''));
        } else {
            warning("{$passed} passed, {$failed} failed." . ($durationText ? " ({$durationText})" : ''));
        }
    }

    /**
     * @param array<string, CheckResult[]> $failedByCategory
     */
    private function renderIssuesTable(array $failedByCategory, bool $verbose): void
    {
        $allRows = [];
        $suggestions = [];

        foreach ($failedByCategory as $category => $results) {
            foreach ($results as $result) {
                $severityIcon = $result->severity->value === 'error' ? '✗' : '!';
                $checkName = $result->check;

                foreach ($result->locations as $loc) {
                    $file = $this->relativePath($loc['file'] ?? '');
                    $line = $loc['line'] ?? '-';
                    $detail = $this->extractLocationDetail($loc);

                    $allRows[] = [
                        $severityIcon,
                        $checkName,
                        $file,
                        $line,
                        $detail,
                    ];
                }

                if (empty($result->locations)) {
                    $allRows[] = [
                        $severityIcon,
                        $checkName,
                        '',
                        '-',
                        $result->message,
                    ];
                }

                if ($result->suggestion) {
                    $suggestions[$checkName] = $result->suggestion;
                }
            }
        }

        // Cap rows when not verbose (prevent flood)
        $totalIssues = count($allRows);
        $capped = ! $verbose && $totalIssues > 20;
        $rows = $capped ? array_slice($allRows, 0, 20) : $allRows;

        warning('Issues Found');

        table(
            ['', 'Check', 'File', 'Line', 'Detail'],
            $rows,
        );

        if ($capped) {
            note("... and " . ($totalIssues - 20) . " more (re-run with -v for the full list)", 'info');
        }

        if (! empty($suggestions)) {
            info('Suggestions');
            foreach ($suggestions as $check => $suggestion) {
                note("{$check}: {$suggestion}", 'info');
            }
        }
    }

    private function relativePath(string $path): string
    {
        $base = base_path();

        if ($base && str_starts_with($path, $base)) {
            return ltrim(substr($path, strlen($base)), '/\\');
        }

        return $path;
    }

    private function extractLocationDetail(array $loc): string
    {
        if (isset($loc['middleware'])) {
            return 'middleware: ' . $loc['middleware'];
        }

        if (isset($loc['uri'], $loc['name'])) {
            $name = $loc['name'] !== '(unnamed)' ? $loc['name'] : $loc['uri'];
            return $name;
        }

        // ValueVsFirstOnNullCheck style
        if (isset($loc['issue'], $loc['value'])) {
            return "{$loc['issue']}: `{$loc['value']}`";
        }

        // View-related
        foreach (['layout', 'view', 'component'] as $key) {
            if (isset($loc[$key])) {
                return $loc[$key];
            }
        }

        // Generic issue or fallback
        return $loc['issue']
            ?? $loc['message']
            ?? $loc['table']
            ?? $loc['column']
            ?? $loc['class']
            ?? $loc['route']
            ?? json_encode($loc, JSON_UNESCAPED_SLASHES);
    }
}
