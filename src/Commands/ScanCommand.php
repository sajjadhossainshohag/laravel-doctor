<?php

namespace SajjadHossain\Doctor\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Laravel\AgentDetector\AgentDetector;
use SajjadHossain\Doctor\CheckRegistry;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\Output\AgentRenderer;
use SajjadHossain\Doctor\Output\ConsoleRenderer;
use SajjadHossain\Doctor\ParallelRunner;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ScanCommand extends Command
{
    protected $signature = 'doctor:scan
        {--only= : Comma-separated check categories to run}
        {--json : Output structured JSON}
        {--html : Output HTML report}
        {--format= : Output format (agent)}
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

        $isAgent = $this->option('format') === 'agent' || AgentDetector::detect()->isAgent;

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

        if (! $isAgent) {
            $this->newLine();
            $this->line("  <fg=cyan>Running {$total} checks...</>");
            $this->newLine();
        }

        if ($this->option('parallel')) {
            $workerCount = $this->option('workers') ? (int) $this->option('workers') : null;

            if (! $isAgent) {
                $this->line("  <fg=cyan>Spawning parallel workers...</>");
                $this->newLine();
            }

            $uncachedAll = [];

            foreach ($grouped as $category => $categoryChecks) {
                if (!$noCache) {
                    $cached = Cache::store($cacheStore)->get($this->computeCacheKey($category));
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

            $workerResults = $runner->run($uncachedAll, function (string $event, int $idx, string $info, bool $success) use ($isAgent): void {
                if ($isAgent) {
                    return;
                }

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
                    Cache::store($cacheStore)->put($this->computeCacheKey($cat), $catResults, $cacheTtl);
                }
            }

            if (! $isAgent) {
                $this->newLine();
            }
        } else {
            $checkIndex = 0;

            foreach ($grouped as $category => $categoryChecks) {
                $cachedResults = null;

                if (!$noCache) {
                    $cachedResults = Cache::store($cacheStore)->get($this->computeCacheKey($category));
                }

                if ($cachedResults !== null) {
                    foreach ($cachedResults as $result) {
                        $checkIndex++;
                        $results[] = $result;

                        if (! $isAgent) {
                            $this->output->write("  <fg=gray>[{$this->pad($checkIndex, $total)}/{$total}]</> {$result->check}... ");
                            $icon = $result->passed ? '✓' : '✗';
                            $color = $result->passed ? 'green' : ($result->severity === Severity::Error ? 'red' : 'yellow');
                            $this->output->writeln("<fg={$color}>{$icon}</> <fg=gray>cached</>");
                        }
                    }

                    continue;
                }

                $freshResults = [];

                foreach ($categoryChecks as $check) {
                    $checkIndex++;
                    $name = $check->name();

                    if (! $isAgent) {
                        $this->output->write("  <fg=gray>[{$this->pad($checkIndex, $total)}/{$total}]</> {$name}... ");
                    }

                    $checkStart = microtime(true);
                    $result = $check->run();
                    $checkDuration = (microtime(true) - $checkStart) * 1000;
                    $freshResults[] = $result;

                    if (! $isAgent) {
                        $icon = $result->passed ? '✓' : '✗';
                        $color = $result->passed ? 'green' : ($result->severity === Severity::Error ? 'red' : 'yellow');
                        $ms = number_format($checkDuration, 0);
                        $this->output->writeln("<fg={$color}>{$icon}</> <fg=gray>{$ms}ms</>");
                    }
                }

                if (!$noCache) {
                    Cache::store($cacheStore)->put($this->computeCacheKey($category), $freshResults, $cacheTtl);
                }

                $results = array_merge($results, $freshResults);
            }

            if (! $isAgent) {
                $this->newLine();
            }
        }

        $duration = (microtime(true) - $overallStart) * 1000;

        if ($isAgent) {
            $this->line((new AgentRenderer())->render($results));

            return $this->failOnExitCode($results);
        }

        if ($this->option('json')) {
            $this->line((new \SajjadHossain\Doctor\Output\JsonRenderer())->render($results));

            return $this->failOnExitCode($results);
        }

        if ($this->option('html')) {
            $this->line((new \SajjadHossain\Doctor\Output\HtmlRenderer())->render($results));

            return $this->failOnExitCode($results);
        }

        $renderer->render($this->output, $results, $duration);

        return $this->failOnExitCode($results);
    }

    private static array $cacheSaltCache = [];

    private function computeCacheKey(string $category): string
    {
        return "doctor_scan_{$category}_" . $this->resolveCacheSalt($category);
    }

    private function resolveCacheSalt(string $category): string
    {
        if ($category === 'env') {
            if (isset(self::$cacheSaltCache['env'])) {
                return self::$cacheSaltCache['env'];
            }

            return self::$cacheSaltCache['env'] = $this->hashFileMtimes([
                base_path('.env'),
                config_path(),
            ]);
        }

        if (! isset(self::$cacheSaltCache['_general'])) {
            self::$cacheSaltCache['_general'] = $this->hashFileMtimes(
                array_merge(config('doctor.scan_paths', [app_path(), resource_path('views')]), [config_path()])
            );
        }

        return self::$cacheSaltCache['_general'];
    }

    private function hashFileMtimes(array $paths): string
    {
        $mtimes = [];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $mtimes[$path] = filemtime($path);
            } elseif (is_dir($path)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
                );
                foreach ($files as $file) {
                    if ($file->isFile()) {
                        $mtimes[$file->getRealPath()] = $file->getMTime();
                    }
                }
            }
        }

        return md5(serialize($mtimes));
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
