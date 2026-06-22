<?php

namespace SajjadHossain\Doctor\Checks\Container;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class SingletonAfterFirstResolveCheck implements HealthCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Singleton Registered in boot() After First Resolve';
    }

    public function category(): string
    {
        return 'container';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: [app_path('Providers')];
        $ignore = config('doctor.ignore.container', []);

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

                $realPath = $file->getRealPath();
                if ($this->isIgnored($realPath, $ignore)) {
                    continue;
                }

                $content = file_get_contents($realPath);
                $stripped = preg_replace('#/\*.*?\*/#s', '', $content);
                $stripped = preg_replace('!//[^\n]*!', '', $stripped);

                // The real issue this check is meant to surface is a
                // singleton being registered in boot() AFTER some other
                // provider has already resolved the abstract during
                // register(). Detecting that reliably requires runtime
                // container tracing. As a heuristic, we only flag when:
                //   1) boot() calls $this->app->singleton(...) AND
                //   2) the SAME file also calls ->make(...), ->resolve(...),
                //      or another singleton/bind for the same abstract in
                //      register() — which would indicate the abstract might
                //      already have been resolved earlier.
                //
                // A singleton in boot() WITHOUT any same-file early resolve
                // is the standard, documented Laravel pattern and is NOT a
                // problem.
                if (! $this->hasBootSingleton($stripped)) {
                    continue;
                }

                $singletonAbstract = $this->extractFirstSingletonAbstract($stripped);
                if ($singletonAbstract === null) {
                    // Could not determine the abstract — we can't verify
                    // it's the same one being resolved in register().
                    continue;
                }

                if ($this->resolveHitsAbstract($stripped, $singletonAbstract)) {
                    $locations[] = [
                        'file' => $realPath,
                        'issue' => "singleton('{$singletonAbstract}') registered in boot() but the same file appears to resolve the same abstract earlier in register()",
                        'value' => 'Move the singleton binding to register() to avoid double construction.',
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
                message: 'No singletons registered in boot() after first resolve detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' singleton(s) registered in boot() after a possible earlier resolve.',
            locations: $locations,
            suggestion: 'Register the singleton in register() so that it is in place before any provider resolves it.',
        );
    }

    private function hasBootSingleton(string $content): bool
    {
        $body = $this->extractMethodBody($content, 'boot');
        if ($body === null) {
            return false;
        }

        return (bool) preg_match('/\$this->app->singleton\s*\(/', $body);
    }

    /**
     * Extract the abstract key from the first $this->app->singleton(...) call
     * in boot(). The first argument may be a quoted string or ::class.
     */
    private function extractFirstSingletonAbstract(string $content): ?string
    {
        $body = $this->extractMethodBody($content, 'boot');
        if ($body === null) {
            return null;
        }
        if (! preg_match('/\$this->app->singleton\s*\(/', $body, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        // Position cursor AT the opening '(' so readBalancedParens works.
        $start = $m[0][1] + strlen($m[0][0]) - 1;
        $args = $this->readBalancedParens($body, $start);
        if ($args === null) {
            return null;
        }
        $parts = $this->splitTopLevelArgs($args);
        $first = trim($parts[0] ?? '');
        if ($first === '') {
            return null;
        }
        if (preg_match('/^[\'"]([^\'"]+)[\'"]\s*$/', $first, $sm)) {
            return $sm[1];
        }
        if (preg_match('/^([\w\\\\]+)::class\s*$/', $first, $cm)) {
            return ltrim($cm[1], '\\');
        }

        return null;
    }

    /**
     * Detect in the same file a possible earlier resolve of the given
     * abstract. Looks only at register() — that's where providers
     * typically resolve things, and anything resolved there is in the
     * container before boot() runs.
     */
    private function resolveHitsAbstract(string $content, string $abstract): bool
    {
        $registerBody = $this->extractMethodBody($content, 'register') ?? '';

        // $this->app->make('foo') / resolve('foo') / bound('foo')
        // We only flag a match when the FIRST argument is a quoted string
        // matching $abstract (case-insensitive, since make/resolve keys
        // are usually lowercase). Without that, we'd over-flag every
        // generic ->make() call.
        $patterns = [
            '/\$this->app->make\s*\(\s*[\'"]' . preg_quote($abstract, '/') . '[\'"]/',
            '/\$this->app->resolve\s*\(\s*[\'"]' . preg_quote($abstract, '/') . '[\'"]/',
            '/\$this->app->bound\s*\(\s*[\'"]' . preg_quote($abstract, '/') . '[\'"]/',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $registerBody)) {
                return true;
            }
        }

        // Also catch ::class form: $this->app->make(Foo::class)
        // Match the short class name against the abstract's last segment.
        $abstractShort = strtolower(basename(str_replace('\\', '/', $abstract)));
        if ($abstractShort !== '' && preg_match('/\$this->app->(?:make|resolve)\s*\(\s*[\w\\\\]+::class\s*\)/', $registerBody, $m)) {
            // Extract the short class name from the first ::class match.
            if (preg_match('/\$this->app->(?:make|resolve)\s*\(\s*([\w\\\\]+)::class\s*\)/', $registerBody, $cm)) {
                $short = strtolower(basename(str_replace('\\', '/', $cm[1])));
                if ($short === $abstractShort) {
                    return true;
                }
            }
        }

        return false;
    }

    private function extractMethodBody(string $content, string $method): ?string
    {
        // Allow optional return type between ) and { so modern
        // `boot(): void` stubs are detected.
        if (! preg_match('/function\s+'.preg_quote($method, '/').'\s*\([^)]*\)\s*(?::\s*[\\\\\w|&\[\]<>,\s]+)?\s*\{(.*?)\n\s*\}/s', $content, $m)) {
            return null;
        }

        return $m[1];
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

    private function isIgnored(string $path, array $patterns): bool
    {
        $normalized = str_replace('\\', '/', $path);
        foreach ($patterns as $pattern) {
            $normalizedPattern = str_replace('\\', '/', $pattern);
            if (fnmatch($normalizedPattern, $normalized) || str_contains($normalized, $normalizedPattern)) {
                return true;
            }
        }
        return false;
    }
}
