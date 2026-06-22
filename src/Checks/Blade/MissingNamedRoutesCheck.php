<?php

namespace SajjadHossain\Doctor\Checks\Blade;

use Illuminate\Support\Facades\Route;
use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class MissingNamedRoutesCheck implements HealthCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Blade Route & URL Issues';
    }

    public function category(): string
    {
        return 'scan';
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $routes = Route::getRoutes();
        $locations = [];
        $scanned = 0;

        foreach ($this->scanPaths ?: config('view.paths', [resource_path('views')]) as $path) {
            if (!is_dir($path)) {
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
                $this->checkRouteCalls($routes, $content, $file->getRealPath(), $locations, $scanned);
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: "All {$scanned} route() references look correct.",
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' route reference(s) with issues.',
            locations: $locations,
            suggestion: 'Define missing named routes or fix invalid route names.',
        );
    }

    private function checkRouteCalls($routes, string $content, string $filePath, array &$locations, int &$scanned): void
    {
        // Strip Blade {{-- comments --}} and PHP // / # line comments so we
        // don't flag commented-out route() / url() calls as live code.
        $stripped = preg_replace('/\{\{--.*?--\}\}/s', '', $content);
        $stripped = preg_replace('!//[^\n]*!', '', $stripped);
        $stripped = preg_replace('/^\s*#[^\n]*$/m', '', $stripped);

        // Use balanced-paren parsing instead of a fixed-shape regex so
        // we correctly handle route('name', $params) as well as the
        // trailing-`)` form.
        if (! preg_match_all('/\broute\s*\(/', $stripped, $calls, PREG_OFFSET_CAPTURE)) {
            return;
        }

        foreach ($calls[0] as [$match, $offset]) {
            $args = $this->readBalancedParens($stripped, $offset + strlen($match) - 1);
            if ($args === null) {
                continue;
            }

            $firstArg = $this->firstStringArg($args);
            if ($firstArg === null) {
                continue;
            }

            $scanned++;
            $name = $firstArg;
            $lineNo = substr_count(substr($stripped, 0, $offset), "\n") + 1;

            $routeExists = $routes->getByName($name) !== null;

            if (!str_starts_with($name, '__') && !$routeExists) {
                $locations[] = [
                    'file' => $filePath,
                    'line' => $lineNo,
                    'issue' => 'undefined named route',
                    'value' => $name,
                ];
                continue;
            }

            if (str_contains($name, '..') || str_starts_with($name, '.') || str_ends_with($name, '.')) {
                $locations[] = [
                    'file' => $filePath,
                    'line' => $lineNo,
                    'issue' => 'invalid route name format',
                    'value' => $name,
                ];
            }
        }
    }

    /**
     * Read balanced parens starting at position $open (which must be at '('),
     * returning the substring between the matching '(' and ')'.
     */
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

    /**
     * Return the first top-level string literal in a comma-separated arg
     * list, or null if the first arg is not a string.
     */
    private function firstStringArg(string $args): ?string
    {
        $first = $this->splitTopLevelArgs($args)[0] ?? '';
        $first = trim($first);
        if (preg_match('/^[\'"]([^\'"]+)[\'"]\s*$/', $first, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Split a comma-separated arg list respecting nested parens / strings.
     */
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
