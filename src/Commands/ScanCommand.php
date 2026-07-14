<?php

namespace SajjadHossain\Doctor\Commands;

use Illuminate\Console\Command;
use SajjadHossain\Doctor\CheckRegistry;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Output\ConsoleRenderer;
use SajjadHossain\Doctor\Enums\Severity;

class ScanCommand extends Command
{
    protected $signature = 'doctor:scan
        {--only= : Comma-separated check categories to run}
        {--json : Output structured JSON}
        {--html : Output HTML report}
        {--fail-on= : Exit non-zero if issues at this severity exist}
        {--no-cache : Skip cached results}';

    protected $description = 'Static analysis — no DB, no HTTP required';

    public function handle(CheckRegistry $registry, ConsoleRenderer $renderer): int
    {
        $checkClasses = $registry->all();
        $only = $this->option('only');
        $instances = [];

        foreach ($checkClasses as $class) {
            $instances[] = new $class();
        }

        if ($only) {
            $categories = explode(',', $only);
            $instances = array_values(array_filter($instances, fn ($c) => in_array($c->category(), $categories, true)));
        }

        if (empty($instances)) {
            $this->warn('No checks registered. Register checks via Doctor::register() in a service provider.');

            return 0;
        }

        $total = count($instances);
        $this->newLine();
        $this->line("  <fg=cyan>Running {$total} checks...</>");
        $this->newLine();

        $results = [];
        $overallStart = microtime(true);

        foreach ($instances as $i => $check) {
            $name = $check->name();
            $this->output->write("  <fg=gray>[{$this->pad($i + 1, $total)}/{$total}]</> {$name}... ");

            $checkStart = microtime(true);
            $result = $check->run();
            $checkDuration = (microtime(true) - $checkStart) * 1000;
            $results[] = $result;

            $icon = $result->passed ? '✓' : '✗';
            $color = $result->passed ? 'green' : ($result->severity === Severity::Error ? 'red' : 'yellow');
            $ms = number_format($checkDuration, 0);
            $this->output->writeln("<fg={$color}>{$icon}</> <fg=gray>{$ms}ms</>");
        }

        $this->newLine();
        $duration = (microtime(true) - $overallStart) * 1000;

        if ($this->option('json')) {
            $this->line((new \SajjadHossain\Doctor\Output\JsonRenderer())->render($results));

            return $this->failOnExitCode($results);
        }

        if ($this->option('html')) {
            $this->line((new \SajjadHossain\Doctor\Output\HtmlRenderer())->render($results));

            return $this->failOnExitCode($results);
        }

        $renderer->render($this->output, $results, $duration, $this->option('verbose'));

        return $this->failOnExitCode($results);
    }

    private function pad(int $current, int $total): string
    {
        return str_pad((string) $current, strlen((string) $total), ' ', STR_PAD_LEFT);
    }

    private function failOnExitCode(array $results): int
    {
        $failOn = $this->option('fail-on');
        if (!$failOn) {
            return 0;
        }

        $levels = explode(',', $failOn);
        foreach ($results as $result) {
            if (!$result->passed && in_array($result->severity->value, $levels, true)) {
                return 1;
            }
        }

        return 0;
    }
}
