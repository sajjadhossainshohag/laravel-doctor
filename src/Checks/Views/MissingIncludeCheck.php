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
        return Severity::Warning;
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
                $stripped = $this->stripComments($content);

                // Support multiple @include forms:
                //   @include('view.name')
                //   @include('view.name', [...])
                preg_match_all('/@include\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,|\))/', $stripped, $matches);

                foreach ($matches[1] as $viewName) {
                    $scanned++;
                    if (!View::exists($viewName)) {
                        $locations[] = [
                            'file' => $file->getRealPath(),
                            'view' => $viewName,
                        ];
                    }
                }
                // Array form: @include(['view' => 'name'], [...])
                if (preg_match_all('/@include\s*\(\s*\[\s*(?:.*?)[\'"]view[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $stripped, $m2)) {
                    foreach ($m2[1] as $viewName) {
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

    private function stripComments(string $content): string
    {
        $content = preg_replace('/\{\{--.*?--\}\}/s', '', $content);
        $content = preg_replace('#/\*.*?\*/#s', '', $content);
        $content = preg_replace('!//[^\n]*!', '', $content);

        return $content;
    }
}