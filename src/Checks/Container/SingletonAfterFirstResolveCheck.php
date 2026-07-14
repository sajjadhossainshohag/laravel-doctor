<?php

namespace SajjadHossain\Doctor\Checks\Container;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class SingletonAfterFirstResolveCheck extends PhpAstCheck
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

        foreach ($this->scanPhpFiles($paths) as $file) {
            if ($this->isIgnored($file['path'], $ignore)) {
                continue;
            }

            $stmts = $this->parse($this->stripComments($file['content']));
            if ($stmts === null) {
                continue;
            }

            $methods = $this->extractMethodBodies($stmts);

            $registerBody = $methods['register'] ?? '';
            $bootBody = $methods['boot'] ?? '';

            // Check boot() has a singleton
            if (preg_match('/\$this->app->singleton\s*\(/', $bootBody) !== 1) {
                continue;
            }

            $singletonAbstract = $this->extractFirstSingletonAbstract($bootBody);
            if ($singletonAbstract === null) {
                continue;
            }

            if ($registerBody !== '' && $this->resolveHitsAbstract($registerBody, $singletonAbstract)) {
                $locations[] = [
                    'file' => $file['path'],
                    'issue' => "singleton('{$singletonAbstract}') registered in boot() but the same file appears to resolve the same abstract earlier in register()",
                    'value' => 'Move the singleton binding to register() to avoid double construction.',
                ];
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
            message: count($locations) . ' singleton(s) registered in boot() after a possible earlier resolve.',
            locations: $locations,
            suggestion: 'Register the singleton in register() so that it is in place before any provider resolves it.',
        );
    }

    private function extractMethodBodies(array $stmts): array
    {
        $bodies = [];
        $visitor = new class($bodies) extends NodeVisitorAbstract {
            private array $bodies;
            public function __construct(array &$bodies) { $this->bodies = &$bodies; }
            public function enterNode(Node $node): void
            {
                if ($node instanceof ClassMethod
                    && $node->name instanceof Node\Identifier
                    && in_array($node->name->toString(), ['register', 'boot'], true)
                    && $node->stmts !== null
                ) {
                    $content = '';
                    foreach ($node->stmts as $stmt) {
                        $content .= (new \PhpParser\PrettyPrinter\Standard())->prettyPrint([$stmt]);
                    }
                    $this->bodies[$node->name->toString()] = $content;
                }
            }
        };

        $this->traverse($stmts, $visitor);

        return $bodies;
    }

    private function extractFirstSingletonAbstract(string $body): ?string
    {
        if (!preg_match('/\$this->app->singleton\s*\(/', $body, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $rest = substr($body, $m[0][1] + strlen($m[0][0]));
        $args = $this->readBalancedParens($rest);

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

    private function resolveHitsAbstract(string $registerBody, string $abstract): bool
    {
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

        return false;
    }

    private function readBalancedParens(string $haystack): string
    {
        $depth = 0;
        $i = 0;
        $inString = false;
        $stringChar = '';
        $len = strlen($haystack);
        $started = false;
        while ($i < $len) {
            $c = $haystack[$i];
            if ($inString) {
                if ($c === '\\') { $i += 2; continue; }
                if ($c === $stringChar) { $inString = false; }
            } else {
                if ($c === '\'' || $c === '"') { $inString = true; $stringChar = $c; }
                elseif ($c === '(') {
                    if (!$started) { $started = true; }
                    $depth++;
                } elseif ($c === ')') {
                    $depth--;
                    if ($depth === 0 && $started) {
                        return substr($haystack, 0, $i);
                    }
                }
            }
            $i++;
        }

        return '';
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
