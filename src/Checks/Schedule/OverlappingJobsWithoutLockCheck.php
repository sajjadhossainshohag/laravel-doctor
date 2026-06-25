<?php

namespace SajjadHossain\Doctor\Checks\Schedule;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class OverlappingJobsWithoutLockCheck implements HealthCheck
{
    private array $scanPaths = [];

    private ?string $consoleRoutePath = null;

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Overlapping Scheduled Jobs Without Lock';
    }

    public function category(): string
    {
        return 'schedule';
    }

    public function severity(): Severity
    {
        return Severity::Info;
    }

    public function run(): CheckResult
    {
        $locations = [];
        // Schedules can live in:
        //   - app/Console/Kernel.php (L10 and earlier)
        //   - app/Console/Commands/* (L11+ individual command schedules)
        //   - app/Providers/* boot() method that calls $schedule->...
        //   - routes/console.php (L11+ declarative schedules via
        //     Schedule::command(...) / use (function ($schedule) {...}) )
        $paths = $this->scanPaths ?: [
            app_path('Console'),
            app_path('Providers'),
        ];
        $filesToScan = [];
        foreach ($paths as $path) {
            if (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) === 'php') {
                // Single-file path (e.g. for testing).
                $filesToScan[] = $path;
                continue;
            }
            if (! is_dir($path)) {
                continue;
            }
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iter as $file) {
                if ($file->getExtension() === 'php') {
                    $filesToScan[] = $file->getRealPath();
                }
            }
        }
        // routes/console.php is a single file (not a directory).
        $consoleRoute = $this->consoleRoutePath ?? base_path('routes/console.php');
        if (is_file($consoleRoute)) {
            $filesToScan[] = $consoleRoute;
        }

        foreach ($filesToScan as $filePath) {
            $content = file_get_contents($filePath);
            $stripped = preg_replace('#/\*.*?\*/#s', '', $content);
            $stripped = preg_replace('!//[^\n]*!', '', $stripped);

            // Match either $schedule->command(...) (instance method) or
            // Schedule::command(...) (facade static call). The OLD
            // regex required `Schedule::->command` which is invalid
            // PHP — the static facade syntax is `Schedule::command`,
            // not `Schedule::->command`.
            if (! preg_match(
                '/(?:\$schedule\s*->|Schedule::)\s*(command|exec|call|job)\b/',
                $stripped
            )) {
                continue;
            }

            // Per-chain analysis: each `Schedule::command(...)->...;`
            // (or `$schedule->...;`) is an independent task chain. The
            // OLD file-level `->withoutOverlapping` detection incorrectly
            // protected every frequent task in a file when ANY task in
            // the file had the call (Bug #10).
            $chains = $this->extractTaskChains($stripped);
            foreach ($chains as $chain) {
                $hasWithoutOverlap = $this->chainHas($chain, 'withoutOverlapping')
                    || $this->chainHas($chain, 'onOneServer');

                $hasFrequent = $this->chainIsFrequent($chain);
                if (! $hasFrequent) {
                    continue;
                }

                if (! $hasWithoutOverlap) {
                    // Try to extract the first quoted argument as the
                    // task identifier (e.g. 'a:do-thing') so the issue
                    // message tells the developer WHICH scheduled
                    // task is unprotected.
                    $taskId = '';
                    // Match either $schedule->command('foo') or
                    // Schedule::command('foo').
                    if (preg_match('/(?:->|::)\s*(?:command|exec|call|job)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $chain['code'], $idm)) {
                        $taskId = $idm[1];
                    }
                    $taskLabel = $taskId !== '' ? "'{$taskId}'" : '?';
                    $locations[] = [
                        'file' => $filePath,
                        'line' => $chain['startLine'],
                        'issue' => "Frequent scheduled task {$taskLabel} without ->withoutOverlapping() / ->onOneServer() — risk of overlapping runs",
                    ];
                }
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No overlapping scheduled jobs without lock detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' frequent task(s) without ->withoutOverlapping() / ->onOneServer().',
            locations: $locations,
            suggestion: 'Add ->withoutOverlapping() (and optionally ->onOneServer()) to prevent task stacking.',
        );
    }

    /**
     * Extract individual scheduled-task chains from a file. Each chain
     * is one `Schedule::command(...)->...;` (or similar) statement with
     * its trailing method calls.
     *
     * @return list<array{method: ?string, code: string, startLine: int}>
     */
    private function extractTaskChains(string $content): array
    {
        $chains = [];
        $len = strlen($content);
        $i = 0;
        while ($i < $len) {
            // Find the next Schedule:: / $schedule-> head.
            if (preg_match(
                '/(?:\$schedule\s*->|Schedule::)\s*(command|exec|call|job)\s*\(/',
                $content,
                $m,
                PREG_OFFSET_CAPTURE,
                $i
            )) {
                $headOffset = $m[0][1];
                $methodName = $m[1][0];
                $startLine = substr_count(substr($content, 0, $headOffset), "\n") + 1;
                $methodName = $m[1][0];
                $startLine = substr_count(substr($content, 0, $headOffset), "\n") + 1;
                // Track the head's open paren to find its matching close,
                // then keep going through chained `->method(...)` calls
                // until we hit a `;` outside any nested closure / paren.
                $stmtStart = $headOffset;
                $depth = 0;
                $braceDepth = 0;
                $inString = false;
                $stringChar = '';
                $j = $headOffset;
                $endOffset = null;
                while ($j < $len) {
                    $c = $content[$j];
                    if ($inString) {
                        if ($c === '\\') { $j += 2; continue; }
                        if ($c === $stringChar) { $inString = false; }
                        $j++;
                        continue;
                    }
                    if ($c === '\'' || $c === '"') {
                        $inString = true;
                        $stringChar = $c;
                        $j++;
                        continue;
                    }
                    if ($c === '(') { $depth++; $j++; continue; }
                    if ($c === ')') { $depth--; $j++; continue; }
                    if ($c === '{') { $braceDepth++; $j++; continue; }
                    if ($c === '}') { $braceDepth--; $j++; continue; }
                    if ($c === ';' && $depth === 0 && $braceDepth === 0) {
                        $endOffset = $j;
                        break;
                    }
                    $j++;
                }
                if ($endOffset === null) {
                    // Unterminated statement — bail to avoid infinite loop.
                    break;
                }
                $code = substr($content, $stmtStart, $endOffset - $stmtStart + 1);
                $chains[] = [
                    'method' => $methodName,
                    'code' => $code,
                    'startLine' => $startLine,
                ];
                $i = $endOffset + 1;
            } else {
                break;
            }
        }

        return $chains;
    }

    /**
     * Whether a chain (a single scheduled-task statement) contains a
     * call to the given method (e.g. 'withoutOverlapping', 'onOneServer').
     */
    private function chainHas(array $chain, string $method): bool
    {
        return (bool) preg_match('/->'.preg_quote($method, '/').'\s*\(/', $chain['code']);
    }

    /**
     * Whether the chain uses a high-frequency trigger (every-minute
     * family or minute-wildcard cron).
     */
    private function chainIsFrequent(array $chain): bool
    {
        $code = $chain['code'];
        if (preg_match(
            '/->(?:everyMinute|everyFiveMinutes|everyTenMinutes|everyFifteenMinutes|everyThirtyMinutes)\s*\(/',
            $code
        )) {
            return true;
        }
        // Cron with a wildcard in the minute slot.
        if (preg_match_all('/->cron\s*\(\s*[\'"]([^\'"]+)[\'"]/', $code, $cronMatches)) {
            foreach ($cronMatches[1] as $cronExpr) {
                $fields = preg_split('/\s+/', trim($cronExpr));
                if (isset($fields[0]) && str_contains($fields[0], '*')) {
                    return true;
                }
            }
        }

        return false;
    }
}