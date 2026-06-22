<?php

namespace SajjadHossain\Doctor\Checks\Jobs;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class BusChainCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Bus Chain Jobs';
    }

    public function category(): string
    {
        return 'jobs';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];

        $paths = config('doctor.scan_paths', [app_path()]);
        foreach ($paths as $path) {
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
                $stripped = preg_replace('#/\*.*?\*/#s', '', $content);
                $stripped = preg_replace('!//[^\n]*!', '', $stripped);

                if (! preg_match_all('/Bus::chain\s*\(/', $stripped, $matches, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                foreach ($matches[0] as [$hit, $offset]) {
                    $args = $this->readBalancedParens($stripped, $offset + strlen($hit) - 1);
                    if ($args === null) {
                        continue;
                    }

                    // The first argument should be an array literal [...].
                    // Split top-level array elements with proper paren /
                    // bracket / string tracking. Each element is either
                    //   - JobClass::class
                    //   - new JobClass(...)
                    //   - a closure
                    // We can only meaningfully verify class references.
                    $array = $this->extractFirstArrayArg($args);
                    if ($array === null) {
                        continue;
                    }

                    foreach ($this->splitArrayElements($array) as $element) {
                        $element = trim($element);
                        if ($element === '') {
                            continue;
                        }

                        // Skip closures and invocations — we only verify
                        // static ::class references and `new` instances.
                        $className = null;

                        // ClassName::class
                        if (preg_match('/^([\w\\\\]+)::class\s*$/', $element, $cm)) {
                            $className = $cm[1];
                        }
                        // new ClassName(...)
                        elseif (preg_match('/^new\s+([\w\\\\]+)\s*\(/', $element, $nm)) {
                            $className = $nm[1];
                        }

                        if ($className === null) {
                            // Closures, variables, etc. — can't verify.
                            continue;
                        }

                        // Resolve short name against `use` imports and the
                        // current namespace.
                        $resolved = $this->resolveClassName($stripped, $className);
                        if ($resolved !== null && class_exists($resolved)) {
                            continue;
                        }

                        $locations[] = [
                            'file' => $file->getRealPath(),
                            'job' => $resolved ?? $className,
                            'issue' => "Chained job class '{$className}' does not exist",
                        ];
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
                message: 'All Bus::chain() job classes exist.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' chained job class(es) not found.',
            locations: $locations,
            suggestion: 'Create the missing job class or fix the chain reference.',
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

    private function extractFirstArrayArg(string $args): ?string
    {
        $trimmed = ltrim($args);
        if (! str_starts_with($trimmed, '[')) {
            return null;
        }
        // Find the matching ]
        $depth = 0;
        $inString = false;
        $stringChar = '';
        $len = strlen($trimmed);
        for ($i = 0; $i < $len; $i++) {
            $c = $trimmed[$i];
            if ($inString) {
                if ($c === '\\') { $i++; continue; }
                if ($c === $stringChar) { $inString = false; }
                continue;
            }
            if ($c === '\'' || $c === '"') { $inString = true; $stringChar = $c; continue; }
            if ($c === '[') { $depth++; continue; }
            if ($c === ']') {
                $depth--;
                if ($depth === 0) {
                    return substr($trimmed, 1, $i - 1);
                }
            }
        }
        return null;
    }

    private function splitArrayElements(string $array): array
    {
        $parts = [];
        $depth = 0;
        $inString = false;
        $stringChar = '';
        $current = '';
        $len = strlen($array);
        for ($i = 0; $i < $len; $i++) {
            $c = $array[$i];
            if ($inString) {
                $current .= $c;
                if ($c === '\\') { $current .= $array[++$i] ?? ''; continue; }
                if ($c === $stringChar) { $inString = false; }
                continue;
            }
            if ($c === '\'' || $c === '"') { $inString = true; $stringChar = $c; $current .= $c; continue; }
            if ($c === '(' || $c === '[' || $c === '{') { $depth++; $current .= $c; continue; }
            if ($c === ')' || $c === ']' || $c === '}') { $depth--; $current .= $c; continue; }
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

    private function resolveClassName(string $content, string $class): ?string
    {
        $class = ltrim($class, '\\');
        if (class_exists($class)) {
            return $class;
        }
        if (preg_match_all('/^\s*use\s+([\w\\\\]+)(?:\s+as\s+\w+)?\s*;/m', $content, $uses)) {
            foreach ($uses[1] as $fqcn) {
                $parts = explode('\\', ltrim($fqcn, '\\'));
                if (end($parts) === $class) {
                    return ltrim($fqcn, '\\');
                }
            }
        }
        if (preg_match('/^\s*namespace\s+([\w\\\\]+);/m', $content, $ns)) {
            return $ns[1] . '\\' . $class;
        }
        return null;
    }
}