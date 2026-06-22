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
        return Severity::Warning;
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

                // Casts can be valid for non-column attributes (e.g. an
                // accessor for a derived value like full_name or a JSON
                // virtual field). Only flag casts whose key matches a
                // $fillable or $appends entry — that's the case where the
                // cast is intended to be persisted.
                $casts = $model->getCasts();
                $persistedKeys = array_merge($fillable, $model->getAppends());
                $persistedKeys = array_map('strtolower', $persistedKeys);

                foreach ($casts as $col => $castType) {
                    $colLower = strtolower($col);
                    if (! in_array($colLower, $columns, true)) {
                        // Skip if the cast key is a non-persisted virtual
                        // attribute (no $fillable/$appends reference).
                        if (! in_array($colLower, $persistedKeys, true)) {
                            continue;
                        }
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
                message: 'All model columns and persisted casts match the database schema.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' column mismatch(es) detected.',
            locations: $locations,
            suggestion: 'Add a migration for the missing column, remove the field from $fillable, or adjust the cast.',
        );
    }

    /**
     * Discover Eloquent models. We can't rely solely on
     * get_declared_classes() because not all model files are loaded yet.
     * We also scan the app/Models directory and instantiate each class we
     * find.
     *
     * @return array<int, string>
     */
    private function discoverModels(): array
    {
        $models = [];
        $declared = get_declared_classes();

        foreach ($declared as $class) {
            if (is_subclass_of($class, Model::class)) {
                $reflection = new \ReflectionClass($class);
                if (! $reflection->isAbstract() && ! $reflection->isTrait()) {
                    $models[] = $class;
                }
            }
        }

        // Also scan app/Models and require_once the files so declared
        // classes are visible. Composer autoloading will then be a no-op.
        $modelsPath = app_path('Models');
        if (is_dir($modelsPath)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($modelsPath, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $content = file_get_contents($file->getRealPath());
                if (! preg_match('/^namespace\s+([\w\\\\]+);/m', $content, $ns)
                    || ! preg_match('/^class\s+(\w+)/m', $content, $cm)) {
                    continue;
                }
                $fqcn = ltrim($ns[1] . '\\' . $cm[1], '\\');
                if (in_array($fqcn, $models, true)) {
                    continue;
                }
                if (! class_exists($fqcn)) {
                    require_once $file->getRealPath();
                }
                if (class_exists($fqcn) && is_subclass_of($fqcn, Model::class)) {
                    $reflection = new \ReflectionClass($fqcn);
                    if (! $reflection->isAbstract() && ! $reflection->isTrait()) {
                        $models[] = $fqcn;
                    }
                }
            }
        }

        return $models;
    }
}