<?php

namespace SajjadHossain\Doctor\Checks\Views;

use Illuminate\Support\Facades\View;
use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class MissingComponentCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Missing @component References';
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

                // Support the @component() function-call form and the
                // array form. Strip comments before scanning so we don't
                // flag commented-out references.
                if (preg_match_all('/@component\s*\(\s*[\'"]([^\'"]+)[\'"]/', $stripped, $m)) {
                    foreach ($m[1] as $componentName) {
                        if (!View::exists($componentName)) {
                            $locations[] = [
                                'file' => $file->getRealPath(),
                                'component' => $componentName,
                            ];
                        }
                    }
                }
                // Array form: @component(['view' => '...', ...])
                if (preg_match_all('/@component\s*\(\s*\[\s*(?:.*?)[\'"]view[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $stripped, $m2)) {
                    foreach ($m2[1] as $componentName) {
                        if (!View::exists($componentName)) {
                            $locations[] = [
                                'file' => $file->getRealPath(),
                                'component' => $componentName,
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
                message: 'All @component references resolve to existing views.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' @component reference(s) point to missing views.',
            locations: $locations,
            suggestion: 'Create the missing view file or correct the @component path.',
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