<?php

namespace SajjadHossain\Doctor\Checks\Schema;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
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
        $declared = $this->modelClasses ?: get_declared_classes();

        foreach ($declared as $class) {
            if (!is_subclass_of($class, Model::class)) {
                continue;
            }

            try {
                $reflection = new \ReflectionClass($class);
                if ($reflection->isAbstract()) {
                    continue;
                }

                $model = new $class();
                $casts = $model->getCasts();

                foreach ($casts as $column => $cast) {
                    // Check built-in types FIRST — before class_exists() — because PHP
                    // class_exists() is case-insensitive and would match "datetime" → DateTime
                    $builtIn = ['array', 'boolean', 'bool', 'collection', 'date', 'datetime',
                        'decimal', 'double', 'encrypted', 'float', 'hashed', 'integer', 'int',
                        'json', 'object', 'real', 'string', 'timestamp', 'immutable_date',
                        'immutable_datetime', 'custom_datetime', ];

                    // Strip precision parameter (e.g. "decimal:2" → "decimal")
                    $baseCast = explode(':', $cast, 2)[0];

                    if (in_array($baseCast, $builtIn, true)) {
                        continue;
                    }

                    if (enum_exists($cast)) {
                        continue;
                    }

                    if (class_exists($cast)) {
                        $implements = class_implements($cast);
                        if (!in_array(CastsAttributes::class, $implements ?: [], true)) {
                            $locations[] = [
                                'model' => $class,
                                'column' => $column,
                                'cast' => $cast,
                                'issue' => 'Cast class does not implement CastsAttributes',
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
        );
    }
}
