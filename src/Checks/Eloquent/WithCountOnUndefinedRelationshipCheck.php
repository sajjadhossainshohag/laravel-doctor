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
        return Severity::Warning;
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
                $stripped = preg_replace('#/\*.*?\*/#s', '', $content);
                $stripped = preg_replace('!//[^\n]*!', '', $stripped);

                // Collect all model imports in this file.
                $modelImports = $this->findModelImports($stripped);

                preg_match_all('/->\s*withCount\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $stripped, $matches, PREG_SET_ORDER);

                foreach ($matches as $m) {
                    $rel = $m[1];
                    $modelClass = $this->guessModelClass($stripped, $modelImports);

                    // Can't reliably determine which model this ->withCount()
                    // call applies to without runtime type information — skip
                    // rather than report a false positive.
                    if ($modelClass === null) {
                        continue;
                    }

                    $existsOnAny = false;
                    foreach ((array) $modelClass as $candidate) {
                        if ($this->relationshipExists($candidate, $rel)) {
                            $existsOnAny = true;
                            break;
                        }
                    }

                    // No "exists on any model" fallback — that previously
                    // produced false positives (matching a same-named
                    // relationship on an unrelated model) and false
                    // negatives (the actual model was not in the scanned
                    // set). Only flag when we have a candidate model and
                    // none of the candidates defines the relationship.
                    if (! $existsOnAny) {
                        $modelLabel = is_array($modelClass) ? implode('|', $modelClass) : $modelClass;
                        $locations[] = [
                            'file' => $file->getRealPath(),
                            'issue' => "withCount('{$rel}') called but no candidate model ({$modelLabel}) declares a '{$rel}' relationship",
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
        // Strategy 1: explicit static ::withCount() — find the exact model.
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
            // Could not resolve — do NOT fall back to all imports.
            return null;
        }

        // Strategy 2: chained ->withCount() — without a clear target model,
        // we can't tell which model is involved. Skip.
        return null;
    }

    private function relationshipExists(string $modelClass, string $relation): bool
    {
        if (! class_exists($modelClass)) {
            return false;
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
}
