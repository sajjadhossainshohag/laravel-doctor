<?php

namespace SajjadHossain\Doctor\Checks\Schema;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class ColumnMismatchCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Column Mismatch (Eloquent vs DB)';
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
        $models = $this->discoverModels();

        foreach ($models as $modelClass) {
            try {
                $model = new $modelClass();
                $table = $model->getTable();

                if (!Schema::hasTable($table)) {
                    $locations[] = [
                        'model' => $modelClass,
                        'table' => $table,
                        'issue' => 'Table does not exist in database',
                    ];
                    continue;
                }

                $columns = Schema::getColumnListing($table);
                $columns = array_map('strtolower', $columns);

                $fillable = $model->getFillable();
                foreach ($fillable as $col) {
                    if (!in_array(strtolower($col), $columns, true)) {
                        $locations[] = [
                            'model' => $modelClass,
                            'table' => $table,
                            'column' => $col,
                            'issue' => '$fillable column not found in DB table',
                        ];
                    }
                }

                $casts = $model->getCasts();
                foreach ($casts as $col => $castType) {
                    if (!in_array(strtolower($col), $columns, true)) {
                        $locations[] = [
                            'model' => $modelClass,
                            'table' => $table,
                            'column' => $col,
                            'cast' => $castType,
                            'issue' => 'Cast column not found in DB table',
                        ];
                    }
                }
            } catch (\Throwable $e) {
                $locations[] = [
                    'model' => $modelClass,
                    'issue' => 'Error inspecting model: ' . $e->getMessage(),
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All model columns and casts match database schema.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' column mismatch(es) detected.',
            locations: $locations,
        );
    }

    private function discoverModels(): array
    {
        $models = [];
        $declared = get_declared_classes();

        foreach ($declared as $class) {
            if (is_subclass_of($class, Model::class)) {
                $reflection = new \ReflectionClass($class);
                if (!$reflection->isAbstract()) {
                    $models[] = $class;
                }
            }
        }

        return $models;
    }
}
