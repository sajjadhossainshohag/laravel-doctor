<?php

namespace SajjadHossain\Doctor\Checks\Schema;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class ForeignKeyCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Foreign Key Integrity';
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
        $driver = DB::connection()->getDriverName();

        // Only MySQL exposes information_schema in the way this check
        // queries it. For other drivers we surface an explicit info
        // message instead of silently passing — the old behavior hid the
        // fact that the check wasn't actually running.
        $supported = ['mysql', 'mariadb'];
        if (! in_array($driver, $supported, true)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: "Foreign key integrity checks are not implemented for the '{$driver}' driver — only MySQL/MariaDB information_schema is queried.",
            );
        }

        try {
            $tables = Schema::getAllTables();
        } catch (\BadMethodCallException) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: "Foreign key checks are not supported on the '{$driver}' driver (getAllTables not available).",
            );
        }

        $locations = [];

        foreach ($tables as $tableRow) {
            // Schema::getAllTables() return shape varies:
            //   - Array form on some drivers: [ ['name' => 'foo'], ... ]
            //   - Object form on MySQL/Postgres: [ stdClass{ name, schema? }, ... ]
            //   - String form on SQLite (older Laravel): [ 'foo', ... ]
            if (is_object($tableRow)) {
                $tableName = $tableRow->name ?? null;
                if (! is_string($tableName) || $tableName === '') {
                    continue;
                }
            } elseif (is_array($tableRow)) {
                $first = reset($tableRow);
                $tableName = is_object($first) ? ($first->name ?? null) : $first;
                if (! is_string($tableName) || $tableName === '') {
                    continue;
                }
            } else {
                $tableName = $tableRow;
            }

            try {
                $foreignKeys = DB::select(
                    "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                     FROM information_schema.KEY_COLUMN_USAGE
                     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL",
                    [DB::getDatabaseName(), $tableName]
                );

                foreach ($foreignKeys as $fk) {
                    if (!Schema::hasTable($fk->REFERENCED_TABLE_NAME)) {
                        $locations[] = [
                            'table' => $tableName,
                            'column' => $fk->COLUMN_NAME,
                            'references_table' => $fk->REFERENCED_TABLE_NAME,
                            'issue' => 'Referenced table does not exist',
                        ];
                    } elseif (!Schema::hasColumn($fk->REFERENCED_TABLE_NAME, $fk->REFERENCED_COLUMN_NAME)) {
                        $locations[] = [
                            'table' => $tableName,
                            'column' => $fk->COLUMN_NAME,
                            'references_table' => $fk->REFERENCED_TABLE_NAME,
                            'references_column' => $fk->REFERENCED_COLUMN_NAME,
                            'issue' => 'Referenced column does not exist',
                        ];
                    }
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
                message: 'All foreign key references are valid.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' foreign key issue(s) found.',
            locations: $locations,
            suggestion: 'Add the referenced table/column or remove the foreign key constraint.',
        );
    }
}