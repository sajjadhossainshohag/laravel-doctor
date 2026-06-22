<?php

namespace SajjadHossain\Doctor\Checks\Cache;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class RememberReturnsClosureCheck implements HealthCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Cache::remember() Returns Closure';
    }

    public function category(): string
    {
        return 'cache';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: config('doctor.scan_paths', [app_path(), resource_path('views')]);

        // Closures stored in in-memory / process-local cache stores (array)
        // are not serialized, so a Closure return is fine there.
        // For persistent stores (file, redis, memcached, database, dynamodb),
        // the cached value is serialized, and Closures cannot be serialized.
        $driver = config('cache.default', 'file');
        $serializingDrivers = ['file', 'redis', 'memcached', 'database', 'dynamodb'];
        $serializes = in_array($driver, $serializingDrivers, true);

        if (! $serializes) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: "Cache driver '{$driver}' does not serialize values; Closures in remember() are safe.",
            );
        }

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
                // Strip block AND line comments before matching — otherwise a
                // commented-out Cache::remember(...) call still triggers the
                // check. Order matters: block comments first so we don't
                // accidentally cut a /* ... */ block in half on a // match.
                $stripped = preg_replace('#/\*.*?\*/#s', '', $content);
                $stripped = preg_replace('!(//|#).*!', '', $stripped);
                if ($this->rememberCallbackReturnsClosure($stripped)) {
                    $locations[] = [
                        'file' => $file->getRealPath(),
                        'issue' => "Cache::remember() callback returns a Closure — will fail to serialize on driver '{$driver}'",
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
                message: "No Cache::remember() callbacks returning Closures detected on driver '{$driver}'.",
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' remember() callback(s) may return a Closure.',
            locations: $locations,
            suggestion: 'Ensure the callback returns serializable data, not a Closure.',
        );
    }

    /**
     * Detect a `Cache::remember(...)` whose callback body actually returns a
     * Closure (either via `function (...) { ... }` or `fn (...) => ...`).
     */
    private function rememberCallbackReturnsClosure(string $content): bool
    {
        if (! preg_match_all('/Cache::remember\s*\(/', $content, $m, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        foreach ($m[0] as [$match, $offset]) {
            // Find the matching close paren, taking string literals and nested
            // parens into account.
            $depth = 0;
            $len = strlen($content);
            $inString = false;
            $stringChar = '';
            $i = $offset + strlen('Cache::remember');
            $argsStart = -1;
            while ($i < $len) {
                $c = $content[$i];
                if ($inString) {
                    if ($c === '\\') { $i += 2; continue; }
                    if ($c === $stringChar) { $inString = false; }
                } else {
                    if ($c === '\'' || $c === '"') { $inString = true; $stringChar = $c; }
                    elseif ($c === '(') { if ($depth === 0) $argsStart = $i + 1; $depth++; }
                    elseif ($c === ')') { $depth--; if ($depth === 0) { $argsEnd = $i; break; } }
                }
                $i++;
            }
            if (! isset($argsEnd)) {
                continue;
            }

            $args = substr($content, $argsStart, $argsEnd - $argsStart);
            $args = trim($args);

            // Last argument of Cache::remember is the closure. It can be:
            //   function (...) { return ...; }
            //   fn (...) => ...
            //   SomeClass::method
            if (preg_match('/\bfunction\s*\([^)]*\)\s*\{/', $args)) {
                // Extract the body of the closure to see if it returns a closure
                if (preg_match('/\bfunction\s*\([^)]*\)\s*\{(.*)\}\s*\)?\s*;?\s*$/s', $args, $bm)) {
                    $body = $bm[1];
                    if (preg_match('/\breturn\s+(?:fn\s*\(|function\s*\()/', $body)) {
                        return true;
                    }
                }
            }
            if (preg_match('/\bfn\s*\([^)]*\)\s*=>/', $args)) {
                // arrow function: only a closure if the body itself is `fn() => ...` or `function() { ... }`
                if (preg_match('/=>\s*(?:fn\s*\(|function\s*\()/', $args)) {
                    return true;
                }
            }
        }

        return false;
    }
}
