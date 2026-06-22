<?php

namespace SajjadHossain\Doctor\Checks\Config;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class AbortIfWrongHttpCodeCheck implements HealthCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'abort_if() / abort_unless() Wrong HTTP Code';
    }

    public function category(): string
    {
        return 'config';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: config('doctor.scan_paths', [app_path(), resource_path('views')]);

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

                // Strip PHP line comments and Blade {{-- --}} comments so we
                // don't flag commented-out code.
                $stripped = preg_replace('!//[^\n]*!', '', $content);
                $stripped = preg_replace('/\{\{--.*?--\}\}/s', '', $stripped);
                // Drop string literals so quoted HTTP codes don't get flagged.
                $stripped = preg_replace("/'[^'\\\\]*(?:\\\\.[^'\\\\]*)*'/", "''", $stripped);
                $stripped = preg_replace('/"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"/', '""', $stripped);

                // Use balanced-paren parsing so the CONDITION argument can
                // contain commas (e.g. abort_if(in_array($x, [1, 2]), 200))
                // without truncating the match at the first comma.
                foreach (['abort_if', 'abort_unless'] as $fn) {
                    if (! preg_match_all('/'.$fn.'\s*\(/', $stripped, $calls, PREG_OFFSET_CAPTURE)) {
                        continue;
                    }
                    foreach ($calls[0] as [$match, $offset]) {
                        // Position the cursor AT the opening '(' so
                        // readBalancedParens can find the matching ')'.
                        $argsStart = $offset + strlen($match) - 1;
                        $args = $this->readBalancedParens($stripped, $argsStart);
                        if ($args === null) {
                            continue;
                        }
                        // Split args and grab the SECOND arg (the HTTP code).
                        $parts = $this->splitTopLevelArgs($args);
                        if (! isset($parts[1])) {
                            continue;
                        }
                        $code = (int) trim($parts[1]);
                        if ($code < 100 || $code > 599) {
                            continue;
                        }
                        if ($this->isBadHttpCode($code)) {
                            $locations[] = [
                                'file' => $file->getRealPath(),
                                'issue' => "{$fn}() with HTTP {$code} — expected 4xx/5xx error code",
                            ];
                        }
                    }
                }
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No suspicious HTTP codes in abort_if/abort_unless calls.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' abort_if/abort_unless call(s) with potentially wrong code.',
            locations: $locations,
            suggestion: 'Use appropriate 4xx (client error) or 5xx (server error) codes.',
        );
    }

    private function isBadHttpCode(int $code): bool
    {
        // 1xx (informational) and 2xx/3xx (success/redirection) are not errors.
        // Only 4xx/5xx are appropriate for abort().
        return $code < 400;
    }

    private function readBalancedParens(string $haystack, int $open): ?string
    {
        if (! isset($haystack[$open]) || $haystack[$open] !== '(') {
            return null;
        }
        $depth = 0;
        $i = $open;
        $inString = false;
        $stringChar = '';
        $len = strlen($haystack);
        while ($i < $len) {
            $c = $haystack[$i];
            if ($inString) {
                if ($c === '\\') { $i += 2; continue; }
                if ($c === $stringChar) { $inString = false; }
            } else {
                if ($c === '\'' || $c === '"') { $inString = true; $stringChar = $c; }
                elseif ($c === '(') { $depth++; }
                elseif ($c === ')') {
                    $depth--;
                    if ($depth === 0) {
                        return substr($haystack, $open + 1, $i - $open - 1);
                    }
                }
            }
            $i++;
        }

        return null;
    }

    private function splitTopLevelArgs(string $args): array
    {
        $parts = [];
        $depth = 0;
        $bracketDepth = 0;
        $inString = false;
        $stringChar = '';
        $current = '';
        $len = strlen($args);
        for ($i = 0; $i < $len; $i++) {
            $c = $args[$i];
            if ($inString) {
                $current .= $c;
                if ($c === '\\') { $current .= ($args[++$i] ?? ''); continue; }
                if ($c === $stringChar) { $inString = false; }
                continue;
            }
            if ($c === '\'' || $c === '"') { $inString = true; $stringChar = $c; $current .= $c; continue; }
            if ($c === '(' || $c === '[') {
                if ($c === '(') $depth++;
                if ($c === '[') $bracketDepth++;
                $current .= $c;
                continue;
            }
            if ($c === ')' || $c === ']') {
                if ($c === ')') $depth--;
                if ($c === ']') $bracketDepth--;
                $current .= $c;
                continue;
            }
            if ($c === ',' && $depth === 0 && $bracketDepth === 0) {
                $parts[] = $current;
                $current = '';
                continue;
            }
            $current .= $c;
        }
        if ($current !== '' || count($parts) > 0) {
            $parts[] = $current;
        }

        return $parts;
    }
}