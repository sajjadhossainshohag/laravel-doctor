<?php

namespace SajjadHossain\Doctor\Checks\Views;

use Illuminate\Support\Facades\View;
use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class MissingIncludeCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Missing @include Views';
    }

    public function category(): string
    {
        return 'views';
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $scanned = 0;
        $locations = [];

        $paths = config('view.paths', [resource_path('views')]);

        foreach ($paths as $path) {
            if (!is_dir($path)) {
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
                preg_match_all('/@include\([\'"]([^\'"]+)[\'"]\)/', $content, $matches);

                foreach ($matches[1] as $viewName) {
                    $scanned++;
                    if (!View::exists($viewName)) {
                        $locations[] = [
                            'file' => $file->getRealPath(),
                            'view' => $viewName,
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
                message: "All {$scanned} @include references resolve to existing views.",
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . " @include reference(s) point to missing views.",
            locations: $locations,
            suggestion: 'Create the missing view file or correct the @include path.',
        );
    }
}
