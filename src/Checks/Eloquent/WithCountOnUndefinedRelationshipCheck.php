<?php

namespace SajjadHossain\Doctor\Checks\Eloquent;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class WithCountOnUndefinedRelationshipCheck implements HealthCheck
{
    public function name(): string
    {
        return 'withCount() on Undefined Relationships';
    }

    public function category(): string
    {
        return 'eloquent';
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = [app_path('Models'), app_path('Http/Controllers')];

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

                // Collect all model imports in this file
                $modelImports = $this->findModelImports($content);

                preg_match_all('/->\s*withCount\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $content, $matches, PREG_SET_ORDER);

                foreach ($matches as $m) {
                    $rel = $m[1];
                    $modelClass = $this->guessModelClass($content, $modelImports);

                    if ($modelClass === null) {
                        // Can't determine model — skip to avoid false positives
                        continue;
                    }

                    // Check relationship on ALL candidate models, not just the first
                    $existsOnAny = false;
                    foreach ((array) $modelClass as $candidate) {
                        if ($this->relationshipExists($candidate, $rel)) {
                            $existsOnAny = true;
                            break;
                        }
                    }

                    if (! $existsOnAny) {
                        // Fallback: check if ANY model in the project has this relationship
                        // (chain may have resolved through an intermediate relation)
                        if ($this->relationshipExistsOnAnyModel($rel, $modelImports)) {
                            continue;
                        }

                        $modelLabel = is_array($modelClass) ? implode('|', $modelClass) : $modelClass;
                        $locations[] = [
                            'file' => $file->getRealPath(),
                            'issue' => "withCount('{$rel}') called but relationship may not exist on {$modelLabel}",
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
                message: 'No suspicious withCount() calls detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' potential withCount() issue(s) detected.',
            locations: $locations,
            suggestion: 'Verify the relationship name exists on the model and withCount() is called before access.',
        );
    }

    /**
     * @return array<int, string>
     */
    private function findModelImports(string $content): array
    {
        $models = [];
        if (preg_match_all('/^use\s+([\w\\\\]+)\s*;/m', $content, $uses)) {
            foreach ($uses[1] as $fqcn) {
                if (class_exists($fqcn) && is_subclass_of($fqcn, 'Illuminate\Database\Eloquent\Model')) {
                    $models[] = ltrim($fqcn, '\\');
                }
            }
        }
        return $models;
    }

    /**
     * @param array<int, string> $modelImports
     * @return string|string[]|null
     */
    private function guessModelClass(string $content, array $modelImports): string|array|null
    {
        // Strategy 1: explicit static ::withCount() — find the exact model
        if (preg_match('/\b(\w+)\s*::\s*withCount\s*\(/', $content, $m)) {
            $shortName = $m[1];
            // Try same-namespace first.
            if (preg_match('/^namespace\s+([\w\\\\]+);/m', $content, $ns)) {
                $candidate = $ns[1] . '\\' . $shortName;
                if (class_exists($candidate) && is_subclass_of($candidate, 'Illuminate\Database\Eloquent\Model')) {
                    return $candidate;
                }
            }
            foreach ($modelImports as $fqcn) {
                if (str_ends_with($fqcn, '\\' . $shortName)) {
                    return $fqcn;
                }
            }
        }

        // Strategy 2: chained ->withCount() — use all imported models
        // (can't reliably determine which model from a chain without deep AST)
        if (! empty($modelImports)) {
            return $modelImports;
        }

        return null;
    }

    private function relationshipExists(string $modelClass, string $relation): bool
    {
        if (! class_exists($modelClass)) {
            return true;
        }
        try {
            $ref = new \ReflectionClass($modelClass);
            foreach ($ref->getMethods() as $method) {
                if ($method->getName() === $relation) {
                    return true;
                }
            }
        } catch (\Throwable) {
        }

        return false;
    }

    /**
     * Check if ANY model in app/Models/ has the given relationship method.
     * This handles chained ->withCount() where the chain resolved to a
     * different model class than the imported ones.
     */
    private function relationshipExistsOnAnyModel(string $relation, array $alreadyChecked): bool
    {
        $modelPath = app_path('Models');
        if (! is_dir($modelPath)) {
            return false;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($modelPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $fqcn = $this->getClassNameFromFile($file->getRealPath());
            if ($fqcn === null || in_array($fqcn, $alreadyChecked, true)) {
                continue;
            }

            if ($this->relationshipExists($fqcn, $relation)) {
                return true;
            }
        }

        return false;
    }

    private function getClassNameFromFile(string $path): ?string
    {
        $content = file_get_contents($path);
        if (! preg_match('/^namespace\s+([\w\\\\]+);/m', $content, $ns)) {
            return null;
        }
        if (! preg_match('/^class\s+(\w+)/m', $content, $cls)) {
            return null;
        }
        $fqcn = $ns[1] . '\\' . $cls[1];

        return class_exists($fqcn) && is_subclass_of($fqcn, 'Illuminate\Database\Eloquent\Model') ? $fqcn : null;
    }
}
