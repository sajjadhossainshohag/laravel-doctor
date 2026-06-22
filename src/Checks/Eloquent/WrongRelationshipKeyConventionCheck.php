<?php

namespace SajjadHossain\Doctor\Checks\Eloquent;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class WrongRelationshipKeyConventionCheck implements HealthCheck
{
    /** @var array<string, list<string>> FK usage per model — tracks polymorphic reuse */
    private array $fkUsage = [];

    public function name(): string
    {
        return 'Wrong Relationship Key Convention';
    }

    public function category(): string
    {
        return 'eloquent';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = [app_path('Models')];

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

                preg_match('/^namespace\s+([\w\\\\]+);/m', $stripped, $nsM);
                preg_match('/^class\s+(\w+)/m', $stripped, $classM);
                $currentModel = $classM[1] ?? '';

                $relations = $this->extractRelations($stripped, $currentModel, $file->getRealPath());
                $locations = array_merge($locations, $relations);
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All relationship key conventions look standard.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' relationship(s) with potentially unconventional foreign keys.',
            locations: $locations,
            suggestion: 'Verify foreign_key matches the expected snake_case convention (e.g. user_id).',
        );
    }

    /**
     * @return list<array{file:string,line:int,method:string,issue:string,value:string}>
     */
    private function extractRelations(string $content, string $currentModel, string $filePath): array
    {
        $relations = [];

        // Find each method body that contains a relationship call.
        if (preg_match_all(
            '/function\s+(\w+)\s*\([^)]*\)\s*\{(.*?)\n\s*\}/s',
            $content,
            $methods,
            PREG_OFFSET_CAPTURE
        )) {
            foreach ($methods[1] as $idx => $nameMatch) {
                $methodName = $nameMatch[0];
                $body = $methods[2][$idx][0];
                $methodOffset = $methods[0][$idx][1];
                $lineNo = substr_count(substr($content, 0, $methodOffset), "\n") + 1;

                if (! preg_match('/\$this->(hasMany|hasOne|belongsTo|belongsToMany|morphTo|morphMany|morphOne)\s*\(/', $body, $relMatch)) {
                    continue;
                }
                $relType = $relMatch[1];
                if ($relType === 'belongsToMany') {
                    continue;
                }

                // Parse the FIRST relationship call's arguments with proper
                // paren/string tracking (the previous regex couldn't handle
                // nested closures or chained methods).
                $callInfo = $this->extractFirstCallArgs($body, $relType);
                if ($callInfo === null) {
                    continue;
                }
                [$args, $fkArgIndex] = $callInfo;

                $foreignKey = $this->extractNthStringArg($args, $fkArgIndex);
                if ($foreignKey === null) {
                    // No explicit FK argument — Laravel will use its
                    // convention. Nothing to flag.
                    continue;
                }

                // Polymorphic relations use different conventions; skip.
                if (in_array($relType, ['morphTo', 'morphMany', 'morphOne'], true)) {
                    continue;
                }

                // Build the expected FK from the related model.
                $expected = null;
                $relatedClass = $this->extractFirstClassArg($args);
                if ($relType === 'belongsTo') {
                    if ($relatedClass !== null) {
                        $expected = $this->snakeCase($this->shortClassName($relatedClass)) . '_id';
                    }
                } else {
                    // hasMany / hasOne: FK is named after the declaring model.
                    $expected = $this->snakeCase($currentModel) . '_id';
                }

                // Must end with _id to be a convention FK at all.
                if (! str_ends_with($foreignKey, '_id')) {
                    continue;
                }
                if ($expected === null) {
                    continue;
                }
                // Exact match — convention is satisfied.
                if ($foreignKey === $expected) {
                    continue;
                }

                // Heuristic for intentional vs. accidental:
                //   - If the FK base looks like a known model name in
                //     app/Models/, the user intentionally pointed at a
                //     different parent — that is fine.
                //   - If the FK base is the SAME as the expected base, the
                //     developer just used a different case/separator — not
                //     a real problem.
                $fkBase = substr($foreignKey, 0, -3);
                $expectedBase = substr($expected, 0, -3);
                if (strcasecmp($fkBase, $expectedBase) === 0) {
                    continue;
                }
                if ($this->looksLikeModelName($fkBase)) {
                    continue;
                }

                $relations[] = [
                    'file' => $filePath,
                    'line' => $lineNo,
                    'method' => $methodName,
                    'issue' => "{$relType}() foreign key '{$foreignKey}' may not follow convention (expected '{$expected}')",
                    'value' => $this->summarizeArgs($args),
                ];
            }
        }

        return $relations;
    }

    /**
     * Return [rawArgs, int $fkArgIndex] for the first matching relationship
     * call. $fkArgIndex is 2 for belongsTo (FK is the 2nd arg) and 3 for
     * hasMany/hasOne/morphX (FK is the 3rd arg, after the related class and
     * the local key).
     */
    private function extractFirstCallArgs(string $body, string $relType): ?array
    {
        if (! preg_match('/\$this->'.preg_quote($relType, '/').'\s*\(/', $body, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $start = $m[0][1] + strlen($m[0][0]);
        $args = $this->readBalancedParens($body, $start);
        if ($args === null) {
            return null;
        }
        $fkIndex = $relType === 'belongsTo' ? 1 : 2; // 0-based: which arg position is the FK

        return [$args, $fkIndex];
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
     * Extract the Nth (0-based) top-level string literal argument from a
     * comma-separated arg list. Returns null if the Nth arg is not a string.
     */
    private function extractNthStringArg(string $args, int $n): ?string
    {
        $parts = $this->splitTopLevelArgs($args);
        if (! isset($parts[$n])) {
            return null;
        }
        $arg = trim($parts[$n]);
        if (preg_match('/^[\'"]([^\'"]+)[\'"]\s*$/', $arg, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Extract the first argument if it is an inline class reference like
     * `Foo::class` or `App\Models\Foo::class`.
     */
    private function extractFirstClassArg(string $args): ?string
    {
        $parts = $this->splitTopLevelArgs($args);
        $first = trim($parts[0] ?? '');
        if (preg_match('/^([\w\\\\]+)::class\s*$/', $first, $m)) {
            return ltrim($m[1], '\\');
        }
        return null;
    }

    /**
     * Split a comma-separated arg list, respecting nested parens / strings /
     * arrays. Used to safely separate relationship arguments.
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
                if ($c === '\\') { $current .= $args[++$i] ?? ''; continue; }
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

    private function looksLikeModelName(string $name): bool
    {
        $studly = str_replace('_', '', ucwords($name, '_'));
        $candidates = [
            app_path('Models/' . $studly . '.php'),
            app_path('Models/' . ucfirst($name) . '.php'),
        ];
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return true;
            }
        }
        return false;
    }

    private function shortClassName(string $fqcn): string
    {
        $parts = explode('\\', ltrim($fqcn, '\\'));

        return end($parts);
    }

    private function snakeCase(string $name): string
    {
        return strtolower(preg_replace('/(?<!^)([A-Z])/', '_$1', $name));
    }

    private function summarizeArgs(string $args): string
    {
        $collapsed = preg_replace('/\s+/', ' ', $args);

        return strlen($collapsed) > 100 ? substr($collapsed, 0, 97).'...' : $collapsed;
    }
}