<?php

namespace SajjadHossain\Doctor\Checks\Storage;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class StoreAsPathTraversalCheck implements HealthCheck
{
    public function name(): string
    {
        return '->storeAs() Path Traversal Risk';
    }

    public function category(): string
    {
        return 'storage';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = config('doctor.scan_paths', [app_path(), resource_path('views')]);

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
                $stripped = preg_replace('#/\*.*?\*/#s', '', $content);
                $stripped = preg_replace('!//[^\n]*!', '', $stripped);

                if (preg_match_all('/->\s*storeAs\s*\(/', $stripped, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as [$hit, $offset]) {
                        $args = $this->readBalancedParens($stripped, $offset + strlen($hit) - 1);
                        if ($args === null) {
                            continue;
                        }

                        $parts = $this->splitArgs($args);
                        $path = $parts[0] ?? '';
                        $name = $parts[1] ?? '';

                        $dangerous = false;
                        $reason = '';

                        foreach ([$path, $name] as $part) {
                            $part = trim($part);
                            if ($part === '') {
                                continue;
                            }
                            // Variable / expression argument — flag as
                            // potentially user-controlled.
                            if (! preg_match('/[\'"]/', $part)) {
                                $dangerous = true;
                                $reason = 'storeAs() argument is a variable — verify it is not user-controlled';
                                break;
                            }
                            // Literal "../" segment.
                            if (preg_match('/[\'"](?:[^\'"\/\\\\]*[\/\\\\])*\.\.(?:[\/\\\\][^\'"]*)?[\'"]/', $part)) {
                                $dangerous = true;
                                $reason = 'storeAs() path contains a literal ".." segment';
                                break;
                            }
                            // Absolute path.
                            if (preg_match('/[\'"]\s*[\/\\\\][^\'"]*[\'"]/', $part)) {
                                $dangerous = true;
                                $reason = 'storeAs() path is absolute';
                                break;
                            }
                        }

                        if ($dangerous) {
                            $locations[] = [
                                'file' => $file->getRealPath(),
                                'issue' => $reason.' — may store to an unexpected location',
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
                message: 'No path traversal risks in storeAs() calls.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' storeAs() call(s) with a path traversal risk.',
            locations: $locations,
            suggestion: 'Sanitize paths and use a dedicated disk. Avoid ".." segments, absolute paths, and unvalidated user input.',
        );
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

    private function splitArgs(string $args): array
    {
        $parts = [];
        $depth = 0;
        $inString = false;
        $stringChar = '';
        $current = '';
        $len = strlen($args);
        for ($i = 0; $i < $len; $i++) {
            $c = $args[$i];
            if ($inString) {
                $current .= $c;
                if ($c === '\\') { $current .= $args[++$i] ?? ''; continue; }
                if ($c === $stringChar) { $inString = false; }
                continue;
            }
            if ($c === '\'' || $c === '"') { $inString = true; $stringChar = $c; $current .= $c; continue; }
            if ($c === '(' || $c === '[') { $depth++; $current .= $c; continue; }
            if ($c === ')' || $c === ']') { $depth--; $current .= $c; continue; }
            if ($c === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';
                continue;
            }
            $current .= $c;
        }
        if (trim($current) !== '' || count($parts) > 0) {
            $parts[] = $current;
        }
        return $parts;
    }
}