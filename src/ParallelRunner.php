<?php

namespace SajjadHossain\Doctor;

class ParallelRunner
{
    private int $workerCount;

    public function __construct(?int $workerCount = null)
    {
        $this->workerCount = $workerCount ?? $this->detectCpuCount();
    }

    public function run(array $checks, ?\Closure $progress = null): array
    {
        if (count($checks) <= 1 || $this->workerCount <= 1) {
            return $this->runChecks($checks);
        }

        $grouped = [];
        foreach ($checks as $check) {
            $grouped[$check->category()][] = $check;
        }

        if (count($grouped) <= 1) {
            return $this->runChecks($checks);
        }

        $workers = min($this->workerCount, count($grouped));
        $groups = $this->balance($grouped, $workers);

        $artisan = base_path('artisan');
        $php = PHP_BINARY;

        $spawned = [];

        foreach ($groups as $i => $group) {
            $categoryStr = implode(',', array_keys($group));
            $tempFile = tempnam(sys_get_temp_dir(), 'doctor_');

            $cmd = sprintf(
                '%s %s doctor:worker --only=%s --output=%s --no-interaction 2>&1',
                escapeshellarg($php),
                escapeshellarg($artisan),
                escapeshellarg($categoryStr),
                escapeshellarg($tempFile)
            );

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $handle = @proc_open($cmd, $descriptors, $pipes);

            if ($progress) {
                $progress('spawn', $i, $categoryStr, is_resource($handle));
            }

            $spawned[] = [
                'handle' => $handle,
                'pipes' => $pipes,
                'tempFile' => $tempFile,
                'group' => $group,
                'categories' => $categoryStr,
            ];
        }

        $allResults = [];

        foreach ($spawned as $i => $proc) {
            if (!is_resource($proc['handle'])) {
                if ($progress) {
                    $progress('fallback', $i, $proc['categories'], false);
                }
                $allResults = array_merge($allResults, $this->runChecks(
                    array_merge(...array_values($proc['group']))
                ));
                continue;
            }

            fclose($proc['pipes'][0]);
            stream_get_contents($proc['pipes'][1]);
            fclose($proc['pipes'][1]);
            fclose($proc['pipes'][2]);

            $exitCode = proc_close($proc['handle']);

            if ($exitCode === 0 && file_exists($proc['tempFile'])) {
                $data = file_get_contents($proc['tempFile']);
                @unlink($proc['tempFile']);

                $workerResults = unserialize($data);
                if (is_array($workerResults)) {
                    $allResults = array_merge($allResults, $workerResults);

                    if ($progress) {
                        $progress('done', $i, $proc['categories'], true);
                    }

                    continue;
                }
            }

            @unlink($proc['tempFile']);

            if ($progress) {
                $progress('fallback', $i, $proc['categories'], false);
            }

            $allResults = array_merge($allResults, $this->runChecks(
                array_merge(...array_values($proc['group']))
            ));
        }

        return $allResults;
    }

    private function runChecks(array $checks): array
    {
        $results = [];
        foreach ($checks as $check) {
            $results[] = $check->run();
        }
        return $results;
    }

    private function balance(array $grouped, int $workers): array
    {
        uasort($grouped, fn ($a, $b) => count($b) <=> count($a));

        $groups = array_fill(0, $workers, []);
        $counts = array_fill(0, $workers, 0);

        foreach ($grouped as $category => $checks) {
            $minIdx = array_search(min($counts), $counts, true);
            $groups[$minIdx][$category] = $checks;
            $counts[$minIdx] += count($checks);
        }

        return array_values(array_filter($groups, fn ($g) => $g !== []));
    }

    private function detectCpuCount(): int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $cores = getenv('NUMBER_OF_PROCESSORS');
            if ($cores !== false && $cores !== '') {
                return max(1, (int) $cores);
            }
        } else {
            $cores = @shell_exec('nproc 2>/dev/null');
            if ($cores !== null && $cores !== '') {
                return max(1, (int) trim($cores));
            }
        }

        return 4;
    }
}
