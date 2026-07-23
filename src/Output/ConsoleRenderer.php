<?php

namespace SajjadHossain\Doctor\Output;

use Illuminate\Console\OutputStyle;
use SajjadHossain\Doctor\DTOs\CheckResult;

class ConsoleRenderer
{
    public function render(OutputStyle $output, array $results, float $duration = 0): void
    {
        $passed = 0;
        $failed = 0;

        foreach ($results as $result) {
            $status = $result->passed ? '✓' : '✗';
            $color = $result->passed ? 'green' : ($result->severity->value === 'error' ? 'red' : 'yellow');

            $output->writeln("  <fg={$color}>{$status}</> {$result->check}");
            $output->writeln("     {$result->message}");

            foreach ($result->locations as $loc) {
                if (isset($loc['file'], $loc['line'], $loc['issue'], $loc['value'])) {
                    $output->writeln("     <fg=gray>  {$loc['file']}:{$loc['line']} — {$loc['issue']}: `{$loc['value']}`</>");
                } elseif (isset($loc['file'], $loc['line'])) {
                    $output->writeln("     <fg=gray>  {$loc['file']}:{$loc['line']}</>");
                } elseif (isset($loc['file'])) {
                    $output->writeln("     <fg=gray>  {$loc['file']}</>");
                } else {
                    $output->writeln("     <fg=gray>  " . json_encode($loc, JSON_UNESCAPED_SLASHES) . "</>");
                }
            }

            if (!$result->passed && $result->suggestion) {
                $output->writeln("     <fg=blue>→ {$result->suggestion}</>");
            }

            if ($result->passed) {
                $passed++;
            } else {
                $failed++;
            }
        }

        $output->newLine();
        $output->writeln("  Results: {$passed} passed, {$failed} failed" . ($duration > 0 ? ' (' . number_format($duration / 1000, 2) . 's)' : ''));
    }
}
