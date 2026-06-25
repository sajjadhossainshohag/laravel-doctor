<?php

namespace SajjadHossain\Doctor\Checks\Eloquent;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class MissingGuardedOrFillableCheck implements HealthCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Missing $guarded or $fillable';
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
                $stripped = preg_replace('#/\*.*?\*/#s', '', $content);
                $stripped = preg_replace('!//[^\n]*!', '', $stripped);

                // Only consider actual Eloquent models. Skip abstract classes
                // and non-Model classes.
                if (! preg_match('/class\s+(\w+)\s+extends\s+Model\b/', $stripped, $classM)) {
                    continue;
                }

                $className = $classM[1];

                // Detect $fillable / $guarded on the class itself, taking
                // any visibility modifier (public / protected / private),
                // any optional type, and any array initializer (including
                // the empty `[]` literal — explicitly setting
                // `$guarded = []` is intentional "mass-assign everything"
                // and counts as a deliberate declaration).
                $hasFillable = (bool) preg_match(
                    '/(?:public|protected|private)\s+(?:\??[\w\\\\|&\[\]]+\s+)?\$fillable\s*=\s*\[/s',
                    $stripped
                );
                $hasGuarded = (bool) preg_match(
                    '/(?:public|protected|private)\s+(?:\??[\w\\\\|&\[\]]+\s+)?\$guarded\s*=\s*\[/s',
                    $stripped
                );
                // Also detect the rare single-string form: $guarded = '*';
                // (Laravel's old default).
                if (! $hasGuarded) {
                    $hasGuarded = (bool) preg_match(
                        '/(?:public|protected|private)\s+(?:\??[\w\\\\|&\[\]]+\s+)?\$guarded\s*=\s*[\'"][^\'"]+[\'"]\s*;/',
                        $stripped
                    );
                }

                $hasUnguardedAttr = (bool) preg_match('/#\s*\[\s*Unguarded\b/', $stripped);
                $hasGuardedAttr = (bool) preg_match('/#\s*\[\s*Guarded\b/', $stripped);

                if ($hasFillable || $hasGuarded || $hasGuardedAttr || $hasUnguardedAttr) {
                    // User has declared one of them — assume they know.
                    continue;
                }

                // Check inherited configurations: walk up the parent chain
                // and look for $fillable / $guarded there. PHP reflection
                // handles traits and deep inheritance correctly.
                if ($this->hasInheritedFillableOrGuarded($className)) {
                    continue;
                }

                // Neither $fillable nor $guarded is declared. This is risky
                // because the model's mass-assignment behaviour is implicit
                // (Laravel's default $guarded = ['*'] applies), and the
                // developer may not have intended that. Flag it.
                $locations[] = [
                    'file' => $file->getRealPath(),
                    'issue' => "Model '{$className}' declares neither \$fillable nor \$guarded — mass-assignment behaviour is implicit and likely unintended",
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All models have appropriate mass-assignment protection.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' model(s) have unsafe mass-assignment configuration.',
            locations: $locations,
            suggestion: 'Add `protected $fillable = [...]` to limit which attributes can be mass-assigned.',
        );
    }

    /**
     * Determine whether the class (or any parent / trait it uses) declares
     * a $fillable or $guarded property. Reflection handles inheritance and
     * traits automatically.
     */
    private function hasInheritedFillableOrGuarded(string $className): bool
    {
        if (! class_exists($className)) {
            // We can't resolve via reflection (e.g. fixtures without
            // autoload) — fall back to scanning the file itself which
            // is what the regex already does, so this returns false
            // here only when the regex above didn't find anything.
            return false;
        }
        try {
            $ref = new \ReflectionClass($className);
            // Check the class itself and all parents for these props.
            $candidates = [$ref];
            while ($parent = $ref->getParentClass()) {
                $candidates[] = $parent;
                $ref = $parent;
            }
            foreach ($candidates as $cls) {
                foreach (['fillable', 'guarded'] as $prop) {
                    if ($cls->hasProperty($prop)) {
                        $p = $cls->getProperty($prop);
                        if ($p->isStatic()) {
                            continue;
                        }
                        // Has the property been given a default value?
                        $defaults = $cls->getDefaultProperties();
                        if (array_key_exists($prop, $defaults)) {
                            return true;
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // ignore reflection errors
        }

        return false;
    }
}