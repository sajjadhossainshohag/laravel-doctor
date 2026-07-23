<?php

namespace SajjadHossain\Doctor\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use SajjadHossain\Doctor\CheckRegistry;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\Output\ConsoleRenderer;
use SajjadHossain\Doctor\ParallelRunner;

class ScanCommand extends Command
{
    protected $signature = 'doctor:scan
        {--only= : Comma-separated check categories to run}
        {--json : Output structured JSON}
        {--html : Output HTML report}
        {--fail-on= : Exit non-zero if issues at this severity exist}
        {--no-cache : Skip cached results}
        {--parallel : Distribute checks across parallel subprocesses}
        {--workers= : Number of parallel workers (detected from CPU by default)}';

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
        $noCache = $this->option('no-cache');

        $grouped = [];
        foreach ($instances as $check) {
            $grouped[$check->category()][] = $check;
        }

        $cacheStore = config('doctor.cache.store', 'file');
        $cacheTtl = config('doctor.cache.ttl', 3600);

        $results = [];
        $overallStart = microtime(true);

        if ($this->option('parallel')) {
            $workerCount = $this->option('workers') ? (int) $this->option('workers') : null;

            $this->newLine();
            $this->line("  <fg=cyan>Spawning parallel workers...</>");
            $this->newLine();

            $uncachedAll = [];

            foreach ($grouped as $category => $categoryChecks) {
                if (!$noCache) {
                    $cached = Cache::store($cacheStore)->get("doctor_scan_{$category}");
                    if ($cached !== null) {
                        $results = array_merge($results, $cached);
                        continue;
                    }
                }
                foreach ($categoryChecks as $check) {
                    $uncachedAll[] = $check;
                }
            }

            $runner = new ParallelRunner($workerCount);

            $workerResults = $runner->run($uncachedAll, function (string $event, int $idx, string $info, bool $success): void {
                match ($event) {
                    'spawn' => $this->line("  Worker " . ($idx + 1) . ": {$info}... " . ($success ? '<fg=green>spawned</>' : '<fg=red>failed</>')),
                    'done' => $this->line("  <fg=gray>Worker " . ($idx + 1) . " completed:</> {$info}"),
                    'fallback' => $this->line("  <fg=yellow>Worker " . ($idx + 1) . " failed, running sequentially: {$info}</>"),
                };
            });

            $results = array_merge($results, $workerResults);

            if (!$noCache) {
                $byCategory = [];
                foreach ($results as $r) {
                    $byCategory[$r->category][] = $r;
                }
                foreach ($byCategory as $cat => $catResults) {
                    Cache::store($cacheStore)->put("doctor_scan_{$cat}", $catResults, $cacheTtl);
                }
            }

            $this->newLine();
        } else {
            $this->newLine();
            $this->line("  <fg=cyan>Running {$total} checks...</>");
            $this->newLine();

            $checkIndex = 0;

            foreach ($grouped as $category => $categoryChecks) {
                $cachedResults = null;

                if (!$noCache) {
                    $cachedResults = Cache::store($cacheStore)->get("doctor_scan_{$category}");
                }

                if ($cachedResults !== null) {
                    foreach ($cachedResults as $result) {
                        $checkIndex++;
                        $results[] = $result;
                        $this->output->write("  <fg=gray>[{$this->pad($checkIndex, $total)}/{$total}]</> {$result->check}... ");
                        $icon = $result->passed ? '✓' : '✗';
                        $color = $result->passed ? 'green' : ($result->severity === Severity::Error ? 'red' : 'yellow');
                        $this->output->writeln("<fg={$color}>{$icon}</> <fg=gray>cached</>");
                    }

                    continue;
                }

                $freshResults = [];

                foreach ($categoryChecks as $check) {
                    $checkIndex++;
                    $name = $check->name();
                    $this->output->write("  <fg=gray>[{$this->pad($checkIndex, $total)}/{$total}]</> {$name}... ");

                    $checkStart = microtime(true);
                    $result = $check->run();
                    $checkDuration = (microtime(true) - $checkStart) * 1000;
                    $freshResults[] = $result;

                    $icon = $result->passed ? '✓' : '✗';
                    $color = $result->passed ? 'green' : ($result->severity === Severity::Error ? 'red' : 'yellow');
                    $ms = number_format($checkDuration, 0);
                    $this->output->writeln("<fg={$color}>{$icon}</> <fg=gray>{$ms}ms</>");
                }

                if (!$noCache) {
                    Cache::store($cacheStore)->put("doctor_scan_{$category}", $freshResults, $cacheTtl);
                }

                $results = array_merge($results, $freshResults);
            }

            $this->newLine();
        }

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
