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

                // Capture BOTH chained ->withCount() and static Model::withCount()
                // forms AND the array form.
                $calls = $this->extractWithCountCalls($stripped);

                foreach ($calls as $call) {
                    $rel = $call['rel'];
                    if ($rel === null) {
                        continue;
                    }
                    $modelClass = $this->guessModelClass($stripped, $modelImports, $call['isStatic']);

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

                // Array form: ->withCount(['posts as p_count', 'comments']).
                // We only check the BASE relationship name ('posts',
                // 'comments') — the alias part is irrelevant to whether
                // the relationship exists.
                if (preg_match_all('/->\s*withCount\s*\(\s*\[(.*?)\]\s*\)/s', $stripped, $arrMatches)) {
                    foreach ($arrMatches[1] as $block) {
                        if (! preg_match_all('/[\'"]([^\'"]+)[\'"]/', $block, $kv)) {
                            continue;
                        }
                        foreach ($kv[1] as $entry) {
                            $base = trim(preg_replace('/\s+as\s+\w+$/i', '', $entry));
                            if ($base === '') {
                                continue;
                            }
                            $candidate = $this->guessModelClass($stripped, $modelImports, false);
                            if ($candidate === null) {
                                continue;
                            }
                            $existsOnAny = false;
                            foreach ((array) $candidate as $cand) {
                                if ($this->relationshipExists($cand, $base)) {
                                    $existsOnAny = true;
                                    break;
                                }
                            }
                            if (! $existsOnAny) {
                                $locations[] = [
                                    'file' => $file->getRealPath(),
                                    'issue' => "withCount([...]) entry '{$base}' does not match a declared relationship",
                                ];
                            }
                        }
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
     * Extract withCount() calls. Returns a list of
     *   [ 'rel' => string|null, 'isStatic' => bool, 'isArray' => bool ]
     * where:
     *   - rel: the relationship name (or null if we couldn't extract one)
     *   - isStatic: true for Model::withCount(), false for ->withCount()
     *   - isArray: true for the array form; rel is null in that case (the
     *     array form is handled separately below).
     *
     * @return list<array{rel: ?string, isStatic: bool, isArray: bool}>
     */
    private function extractWithCountCalls(string $content): array
    {
        $calls = [];

        // Chained single-arg: ->withCount('posts')
        if (preg_match_all('/->\s*withCount\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $content, $m)) {
            foreach ($m[1] as $rel) {
                $calls[] = ['rel' => $rel, 'isStatic' => false, 'isArray' => false];
            }
        }

        // Static single-arg: Model::withCount('posts')
        if (preg_match_all('/\b(\w+)\s*::\s*withCount\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $content, $m2)) {
            foreach ($m2[2] as $rel) {
                $calls[] = ['rel' => $rel, 'isStatic' => true, 'isArray' => false];
            }
        }

        return $calls;
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
    private function guessModelClass(string $content, array $modelImports, bool $isStatic): string|array|null
    {
        if ($isStatic) {
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
            return null;
        }

        // Strategy 2: chained ->withCount() — try to discover the model by
        // looking for static ::query() / ::with() / etc. on the same line,
        // or by detecting $variable that was assigned from a known model
        // earlier in the file. As a heuristic, fall back to "all model
        // imports" — better to over-report (false positive on unrelated
        // models) than to silently miss every chained call.
        // To avoid the original false-positive problem (any model with the
        // relationship matching), we instead look for a line-level
        // indicator of which model this call operates on.
        //
        // If we cannot find a clear indicator, we return null and skip the
        // call entirely. This is the conservative, no-false-positive path.

        // Try to find a static ::query() / ::with() / similar preceding
        // the chained call, e.g.:
        //   User::query()->withCount('posts')->get();
        // The chained receiver is the result of ::query(), so we can pick
        // up the model name from the static call.
        if (preg_match_all('/\b(\w+)\s*::\s*(?:query|with|where|orderBy|select)\s*\([^)]*\)\s*->\s*withCount\s*\(/', $content, $staticCalls)) {
            $candidates = [];
            foreach ($staticCalls[1] as $shortName) {
                if (preg_match('/^namespace\s+([\w\\\\]+);/m', $content, $ns)) {
                    $candidate = $ns[1] . '\\' . $shortName;
                    if (class_exists($candidate) && is_subclass_of($candidate, 'Illuminate\Database\Eloquent\Model')) {
                        $candidates[] = $candidate;
                        continue;
                    }
                }
                foreach ($modelImports as $fqcn) {
                    if (str_ends_with($fqcn, '\\' . $shortName)) {
                        $candidates[] = $fqcn;
                        break;
                    }
                }
            }
            if (! empty($candidates)) {
                return array_values(array_unique($candidates));
            }
        }

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
