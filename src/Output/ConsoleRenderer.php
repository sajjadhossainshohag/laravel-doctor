<?php

namespace SajjadHossain\Doctor\Output;

use Illuminate\Console\OutputStyle;
use Laravel\Prompts\Themes\Default\Concerns\DrawsBoxes;
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
        $failedDetails = [];

        // Group by category
        $grouped = $this->groupByCategory($results);

        // Summary table
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
                $failedDetails[] = $result;
            }
        }

        // Intro
        info('Laravel Doctor — Code Health Scan');

        // Results table
        table(
            ['', 'Category', 'Check', 'Severity'],
            $rows,
        );

        // Failed check details
        if (! empty($failedDetails)) {
            foreach ($failedDetails as $result) {
                $this->renderFailure($result, $verbose);
            }
        }

        // Summary
        $durationText = $duration > 0 ? number_format($duration / 1000, 2) . 's' : '';

        if ($failed === 0) {
            info("All {$passed} checks passed." . ($durationText ? " ({$durationText})" : ''));
        } else {
            warning("{$passed} passed, {$failed} failed." . ($durationText ? " ({$durationText})" : ''));
        }
    }

    private function renderFailure(CheckResult $result, bool $verbose): void
    {
        $level = $result->severity->value === 'error' ? 'error' : 'warning';
        $renderer = $level === 'error' ? '\Laravel\Prompts\error' : '\Laravel\Prompts\warning';

        $renderer($result->check);
        note($result->message, $level);

        if (! empty($result->locations)) {
            $locRows = [];
            foreach (array_slice($result->locations, 0, $verbose ? 100 : 5) as $loc) {
                $file = $loc['file'] ?? '';
                $line = $loc['line'] ?? '';
                $issue = $loc['issue'] ?? ($loc['view'] ?? $loc['layout'] ?? $loc['component'] ?? $loc['middleware'] ?? '');
                $locRows[] = [$file, $line, $issue];
            }

            if (! empty($locRows)) {
                table(
                    ['File', 'Line', 'Detail'],
                    $locRows,
                );
            }

            if (! $verbose && count($result->locations) > 5) {
                note('... and ' . (count($result->locations) - 5) . ' more (use -v for full list)', 'info');
            }
        }

        if ($result->suggestion) {
            note($result->suggestion, $level);
        }
    }

    /**
     * @param CheckResult[] $results
     * @return array<string, CheckResult[]>
     */
    private function groupByCategory(array $results): array
    {
        $grouped = [];
        foreach ($results as $result) {
            $grouped[$result->category][] = $result;
        }
        return $grouped;
    }
}
