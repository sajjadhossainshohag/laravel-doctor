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
                preg_match('/class\s+(\w+)/', $content, $classM);
                $currentModel = $classM[1] ?? '';

                $relations = $this->extractRelations($content, $currentModel, $file->getRealPath());
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

        preg_match_all(
            '/function\s+(\w+)\s*\([^)]*\)\s*\{(.*?)\n\s*\}/s',
            $content,
            $methods,
            PREG_OFFSET_CAPTURE
        );

        foreach ($methods[1] as $idx => $nameMatch) {
            $methodName = $nameMatch[0];
            $body = $methods[2][$idx][0];

            if (! preg_match('/\$this->(hasMany|hasOne|belongsTo|belongsToMany)\s*\((.*?)\)\s*;/s', $body, $callM)) {
                continue;
            }

            $relType = $callM[1];
            if ($relType === 'belongsToMany') {
                continue;
            }

            $args = $callM[2];
            $methodOffset = $methods[0][$idx][1];
            $lineNo = substr_count(substr($content, 0, $methodOffset), "\n") + 1;

            // Extract the FIRST string argument (the foreign key) — but only from
            // the relationship call itself, not from chained methods like ->orderBy().
            $foreignKey = $this->extractFirstStringArg($args);

            // If no explicit FK argument given, Laravel infers convention — skip
            if ($foreignKey === null) {
                continue;
            }

            $expected = null;
            if ($relType === 'belongsTo') {
                if (preg_match('/([\w\\\\]+)::class/', $args, $relatedM)) {
                    $relatedClass = $relatedM[1];
                    $relatedShort = $this->shortClassName($relatedClass);
                    $expected = $this->snakeCase($relatedShort) . '_id';
                }
            } else {
                $expected = $this->snakeCase($currentModel) . '_id';
            }

            // Skip if FK doesn't end with _id (clearly intentional, not convention)
            if (!str_ends_with($foreignKey, '_id')) {
                continue;
            }

            // Skip if expected couldn't be determined
            if ($expected === null) {
                continue;
            }

            // Matches convention — skip
            if ($foreignKey === $expected) {
                continue;
            }

            // Skip polymorphic/semantic FKs: if the FK doesn't match ANY model name
            // pattern, it's intentionally different (e.g. from_user_id, ref_id,
            // referral_target_id, profit_from, target_id)
            $fkBase = substr($foreignKey, 0, -3);
            $expectedBase = substr($expected, 0, -3);
            if ($fkBase !== $expectedBase && !$this->looksLikeModelName($fkBase)) {
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

        return $relations;
    }

    private function looksLikeModelName(string $name): bool
    {
        // A convention FK base should be a singular snake_case that could be a model:
        // e.g. 'user', 'course', 'category', 'transaction'
        // Non-model-like: 'from_user', 'ref', 'profit', 'target', 'referral_target'
        // Simple heuristic: check if there's a model class with this name
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

    private function extractFirstStringArg(string $args): ?string
    {
        // Find the first string literal that is NOT inside a nested chained call
        // Approach: strip all nested parentheses first, then find first string
        $stripped = $args;
        $prev = '';
        while ($prev !== $stripped) {
            $prev = $stripped;
            $stripped = preg_replace('/\([^()]*\)/', '', $stripped);
        }

        if (preg_match('/[\'"]([^\'"]+)[\'"]/', $stripped, $m)) {
            return $m[1];
        }

        return null;
    }

    private function isIntentional(array $rel): bool
    {
        $fk = $rel['foreignKey'];
        $model = $rel['method']; // method name on the model

        // 1. Polymorphic: FK used by multiple relationships on the same model
        //    (We check by current method's declaring class — approximated via file)
        // 2. FK doesn't end with '_id' — clearly not a convention FK
        if (!str_ends_with($fk, '_id')) {
            return true;
        }

        // 3. FK matches no possible model name (e.g. 'from_user_id' — 'FromUser' is not a model)
        $fkBase = substr($fk, 0, -3); // strip '_id'
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $fkBase)) {
            return true;
        }

        // 4. Multiple different FK patterns on the same model suggest polymorphism
        $modelClass = $this->findModelClass($rel['file']);
        if ($modelClass !== null) {
            $fks = $this->fkUsage[$modelClass] ?? [];
            $uniqueFks = array_unique($fks);
            if (count($uniqueFks) > 1) {
                // Check if any FK matches convention — if so, the non-matching ones are intentional
                $hasStandard = false;
                foreach ($uniqueFks as $ufk) {
                    if ($ufk === $this->snakeCase($modelClass) . '_id') {
                        $hasStandard = true;
                        break;
                    }
                }
                if ($hasStandard) {
                    return true;
                }
            }
        }

        return false;
    }

    private function findModelClass(string $file): ?string
    {
        if (!file_exists($file)) {
            return null;
        }
        $content = file_get_contents($file);
        if (preg_match('/^namespace\s+([\w\\\\]+);/m', $content, $ns) &&
            preg_match('/^class\s+(\w+)/m', $content, $class)) {
            return $ns[1] . '\\' . $class[1];
        }
        return null;
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
