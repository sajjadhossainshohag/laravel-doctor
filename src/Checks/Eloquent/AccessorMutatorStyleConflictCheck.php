<?php

namespace SajjadHossain\Doctor\Checks\Eloquent;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class AccessorMutatorStyleConflictCheck implements HealthCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Accessor/Mutator Style Conflict';
    }

    public function category(): string
    {
        return 'eloquent';
    }

    public function severity(): Severity
    {
        return Severity::Info;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: [app_path('Models')];

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

                // Resolve FQCN for reflection-based detection.
                if (! preg_match('/^\s*namespace\s+([\w\\\\]+)\s*;/m', $content, $nsM)
                    || ! preg_match('/^\s*(?:final\s+|abstract\s+)?(?:readonly\s+)?class\s+(\w+)/m', $content, $classM)) {
                    continue;
                }
                $uses = [];
                if (preg_match_all('/^\s*use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/m', $content, $useMatches)) {
                    foreach ($useMatches[1] as $i => $fqcn) {
                        $alias = $useMatches[2][$i] ?? null;
                        $short = $alias ?? basename(str_replace('\\', '/', $fqcn));
                        $uses[$short] = ltrim($fqcn, '\\');
                    }
                }
                $shortClass = $classM[1];
                $fqcn = $uses[$shortClass] ?? ($nsM[1].'\\'.$shortClass);
                if (! class_exists($fqcn)) {
                    continue;
                }

                try {
                    $reflection = new \ReflectionClass($fqcn);
                } catch (\Throwable) {
                    continue;
                }
                if ($reflection->isAbstract() || $reflection->isTrait()) {
                    continue;
                }

                // Old-style accessors: methods named getXxxAttribute /
                // setXxxAttribute. Reflection-based detection so methods
                // declared in the class (regardless of return type or
                // arrow-fn body) are visible.
                $oldAccessors = []; // attribute => [get|set]
                foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                    if ($method->getDeclaringClass()->getName() !== $fqcn) {
                        // Only methods declared in THIS class — inherited
                        // methods (e.g. Model's own accessors) shouldn't
                        // count as a conflict source.
                        continue;
                    }
                    $name = $method->getName();
                    if (preg_match('/^get(\w+)Attribute$/', $name, $am)) {
                        $attr = lcfirst($am[1]);
                        $oldAccessors[$attr] = ($oldAccessors[$attr] ?? '') . 'get';
                    } elseif (preg_match('/^set(\w+)Attribute$/', $name, $sm)) {
                        $attr = lcfirst($sm[1]);
                        $oldAccessors[$attr] = ($oldAccessors[$attr] ?? '') . 'set';
                    }
                }

                // New-style accessors: any Attribute::make(...) call in
                // the source. We can't use reflection for these because
                // they're a method call inside another method — scan the
                // source text for them, but inspect each call's get/set
                // keys to know which attributes are involved.
                $newAccessors = $this->extractAttributeMakeAccessors($content);

                // A conflict exists when the SAME attribute has BOTH an
                // old-style method AND a new-style Attribute::make binding.
                $conflicts = [];
                foreach ($oldAccessors as $attr => $flags) {
                    $newFlags = $newAccessors[$attr] ?? '';
                    if ($newFlags !== '') {
                        $conflicts[] = $attr.' (old: '.implode('+', str_split($flags)).', new: '.implode('+', str_split($newFlags)).')';
                    }
                }

                if (! empty($conflicts)) {
                    $locations[] = [
                        'file' => $file->getRealPath(),
                        'issue' => 'Model declares old-style getXxxAttribute/setXxxAttribute methods AND new-style Attribute::make() bindings for the same attribute(s): '.implode(', ', $conflicts),
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
                message: 'No accessor/mutator style conflicts on the same attribute detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' model(s) define old and new accessor/mutator styles for the same attribute.',
            locations: $locations,
            suggestion: 'Pick one style per attribute. Mixing styles across different attributes is fine.',
        );
    }

    /**
     * Scan source text for `Attribute::make(get: ..., set: ...)` calls and
     * return a map of attribute => 'g'|'s'|'gs' indicating which directions
     * are defined.
     *
     * @return array<string, string>
     */
    private function extractAttributeMakeAccessors(string $content): array
    {
        $stripped = preg_replace('#/\*.*?\*/#s', '', $content);
        $stripped = preg_replace('!//[^\n]*!', '', $stripped);

        $result = [];
        // Match `Attribute::make(` and walk balanced parens to its end, then
        // look for get:/set: keys (string or unquoted) within those args.
        if (! preg_match_all('/Attribute::make\s*\(/', $stripped, $calls, PREG_OFFSET_CAPTURE)) {
            return $result;
        }
        foreach ($calls[0] as [$match, $offset]) {
            $start = $offset + strlen($match) - 1; // point AT '('
            $args = $this->readBalancedParens($stripped, $start);
            if ($args === null) {
                continue;
            }
            $flags = '';
            if (preg_match('/\bget\s*:/', $args)) {
                $flags .= 'g';
            }
            if (preg_match('/\bset\s*:/', $args)) {
                $flags .= 's';
            }
            // We don't know the ATTRIBUTE NAME from the Attribute::make
            // call alone — Laravel uses a static method like
            // `protected function name(): Attribute { return Attribute::make(...); }`
            // and we treat the method name as the attribute. We look back
            // from the call offset for the nearest `function <name>()` to
            // extract it.
            $attrName = $this->attributeNameForMakeCall($stripped, $offset);
            if ($attrName === null || $flags === '') {
                continue;
            }
            $result[$attrName] = $flags;
        }

        return $result;
    }

    private function attributeNameForMakeCall(string $content, int $offset): ?string
    {
        // Walk backwards to find the IMMEDIATELY enclosing
        // `function NAME(...): T {` (return type allowed) that wraps the
        // Attribute::make(...) call. We anchor on the LAST `function NAME`
        // start before the offset so we don't capture a previous method.
        $before = substr($content, 0, $offset);
        if (! preg_match_all('/function\s+(\w+)\s*\(/', $before, $fns, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $last = end($fns[1]);
        if ($last === false) {
            return null;
        }
        // Back up to the start of the `function` keyword.
        $funcStart = strrpos(substr($content, 0, $last[1]), 'function');
        if ($funcStart === false) {
            return null;
        }
        // Confirm there's an opening { somewhere after the signature
        // (skipping an optional return type).
        if (! preg_match('/function\s+\w+\s*\([^)]*\)\s*(?::\s*[\w\\\\|&\[\]<>,\s]+)?\s*\{/', substr($content, $funcStart), $fm)) {
            return null;
        }

        return lcfirst($last[0]);
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
}
