<?php

namespace SajjadHossain\Doctor\Checks\Schema;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\CastsInboundAttributes;
use Illuminate\Database\Eloquent\Model;
use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class InvalidCastsCheck implements HealthCheck
{
    private array $modelClasses = [];

    public function withModels(array $classes): static
    {
        $this->modelClasses = $classes;
        return $this;
    }

    public function name(): string
    {
        return 'Invalid Casts';
    }

    public function category(): string
    {
        return 'schema';
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $declared = $this->discoverModels();

        // Built-in casts (Laravel 9+). 'real' is an alias for 'float' on
        // some platforms, but we include it for safety.
        $builtIn = [
            'array', 'boolean', 'bool', 'collection', 'date', 'datetime',
            'decimal', 'double', 'encrypted', 'encrypted:array',
            'encrypted:collection', 'encrypted:object', 'float', 'hashed',
            'integer', 'int', 'json', 'object', 'real', 'string',
            'timestamp', 'immutable_date', 'immutable_datetime',
            'custom_datetime', 'Illuminate\Database\Eloquent\Casts\AsArrayObject',
            'Illuminate\Database\Eloquent\Casts\AsCollection',
            'Illuminate\Database\Eloquent\Casts\AsEnumArrayObject',
            'Illuminate\Database\Eloquent\Casts\AsEnumCollection',
            'Illuminate\Database\Eloquent\Casts\AsStringable',
            'Illuminate\Database\Eloquent\Casts\AsHash',
            'Illuminate\Database\Eloquent\Casts\AsUri',
            'Illuminate\Database\Eloquent\Casts\AsUrl',
            'Illuminate\Database\Eloquent\Casts\AsImage',
        ];

        foreach ($declared as $class) {
            if (!is_subclass_of($class, Model::class)) {
                continue;
            }

            try {
                $reflection = new \ReflectionClass($class);
                if ($reflection->isAbstract() || $reflection->isTrait()) {
                    continue;
                }

                $model = new $class();
                $casts = $model->getCasts();

                foreach ($casts as $column => $cast) {
                    // Cast definition may be a string 'classname' or 'classname:arg'.
                    // $cast may also be a real class object (Castable returns
                    // the cast class instance via the casts array).
                    if (is_object($cast)) {
                        // A Castable returns its underlying cast instance.
                        if ($cast instanceof CastsAttributes || $cast instanceof CastsInboundAttributes) {
                            continue;
                        }
                        $locations[] = [
                            'model' => $class,
                            'column' => $column,
                            'cast' => $cast::class,
                            'issue' => 'Cast value does not implement CastsAttributes or CastsInboundAttributes',
                        ];
                        continue;
                    }

                    if (! is_string($cast)) {
                        $locations[] = [
                            'model' => $class,
                            'column' => $column,
                            'cast' => (string) $cast,
                            'issue' => 'Cast value is not a string or cast instance',
                        ];
                        continue;
                    }

                    // Strip the :argument portion (e.g. "decimal:2",
                    // "App\Casts\Money:USD"). We use it later to confirm
                    // the class is valid even with arguments.
                    $parts = explode(':', $cast, 2);
                    $baseCast = $parts[0];
                    $castArgs = $parts[1] ?? null;

                    if (in_array($baseCast, $builtIn, true)) {
                        continue;
                    }

                    // Enums: BackedEnum subclasses are valid cast targets.
                    if (enum_exists($baseCast)) {
                        continue;
                    }

                    if (class_exists($baseCast)) {
                        $implements = class_implements($baseCast);
                        $valid = in_array(CastsAttributes::class, $implements ?: [], true)
                            || in_array(CastsInboundAttributes::class, $implements ?: [], true);
                        if (! $valid) {
                            // Castable is acceptable: Laravel resolves
                            // Castable::castUsing() to obtain the actual
                            // cast class.
                            $valid = in_array(Castable::class, $implements ?: [], true);
                        }
                        if (! $valid) {
                            $locations[] = [
                                'model' => $class,
                                'column' => $column,
                                'cast' => $cast,
                                'issue' => 'Cast class does not implement CastsAttributes, CastsInboundAttributes, or Castable',
                            ];
                        }
                        continue;
                    }

                    $locations[] = [
                        'model' => $class,
                        'column' => $column,
                        'cast' => $cast,
                        'issue' => 'Unknown cast type — not a built-in, enum, or cast class',
                    ];
                }
            } catch (\Throwable) {
                continue;
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All casts reference valid types, enum classes, or casters.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' invalid cast(s) detected.',
            locations: $locations,
            suggestion: 'Use a built-in cast, an enum, or a class implementing CastsAttributes/CastsInboundAttributes/Castable.',
        );
    }

    /**
     * @return array<int, string>
     */
    private function discoverModels(): array
    {
        if (! empty($this->modelClasses)) {
            return $this->modelClasses;
        }

        // Fall back to scanning app/Models. Previously this method only
        // returned get_declared_classes(), which is empty unless something
        // has already loaded the model classes — making the check a no-op
        // for typical apps.
        $models = [];
        $modelDir = app_path('Models');
        if (is_dir($modelDir)) {
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($modelDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iter as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $contents = file_get_contents($file->getRealPath());
                if (! preg_match('/^\s*namespace\s+([\w\\\\]+)\s*;/m', $contents, $ns)
                    || ! preg_match('/^\s*(?:final\s+|abstract\s+)?(?:readonly\s+)?class\s+(\w+)/m', $contents, $cm)) {
                    continue;
                }
                $candidate = ltrim($ns[1], '\\').'\\'.$cm[1];
                if (class_exists($candidate)) {
                    $models[] = $candidate;
                }
            }
        }

        // Last resort: anything declared so far.
        if (empty($models)) {
            $models = get_declared_classes();
        }

        return $models;
    }
}