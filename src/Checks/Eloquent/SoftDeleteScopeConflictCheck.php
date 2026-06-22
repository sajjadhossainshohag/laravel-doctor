<?php

namespace SajjadHossain\Doctor\Checks\Eloquent;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class SoftDeleteScopeConflictCheck implements HealthCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Soft Delete Scope Conflicts';
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
        $paths = $this->scanPaths ?: config('doctor.scan_paths', [app_path(), resource_path('views')]);

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

                // Only flag a real conflict. The classic mistake is calling
                // withTrashed() (or onlyTrashed()) AND manually writing
                // ->where('deleted_at', ...) on a model that uses the
                // SoftDeletes trait — the manual filter is then redundant
                // with the global scope / withTrashed behaviour.
                //
                // We do NOT flag:
                //   - query-builder use (no SoftDeletes context)
                //   - intentional manual filtering (no withTrashed/onlyTrashed)
                //   - controllers that may legitimately query any way they
                //     want for reports / admin tasks.

                $usesSoftDeletes = (bool) preg_match('/use\s+SoftDeletes\s*;/', $stripped);
                $usesWithTrashed = (bool) preg_match('/->\s*(withTrashed|onlyTrashed)\s*\(/', $stripped);
                $manualDeletedAt = (bool) preg_match('/where\s*\(\s*[\'"]deleted_at[\'"]/', $stripped);

                if ($usesSoftDeletes && $usesWithTrashed && $manualDeletedAt) {
                    $locations[] = [
                        'file' => $file->getRealPath(),
                        'issue' => 'SoftDeletes model uses withTrashed()/onlyTrashed() together with a manual where(\'deleted_at\', ...) — manual filter is redundant with the global scope',
                        'value' => 'where(\'deleted_at\', ...)',
                    ];
                }
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No soft-delete scope conflicts detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' soft-delete scope conflict(s) detected.',
            locations: $locations,
            suggestion: 'Avoid combining withTrashed()/onlyTrashed() with a manual where(\'deleted_at\', ...) clause on SoftDeletes models.',
        );
    }
}
