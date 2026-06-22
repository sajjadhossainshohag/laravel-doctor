<?php

namespace SajjadHossain\Doctor\Checks\Validation;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class DeprecatedErrorApiCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Deprecated $this->error() in after() Hook';
    }

    public function category(): string
    {
        return 'validation';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = [app_path('Http/Requests')];

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

                // Extract the after() method body and only flag $this->error() that
                // lives inside it. The previous version scanned the entire
                // file and flagged any $this->error() call as long as the
                // file also happened to contain an after() method,
                // regardless of where the call lived. Allow an optional
                // return type between ) and { so `function after(): void`
                // stubs are detected.
                if (! preg_match('/function\s+after\s*\([^)]*\)\s*(?::\s*[\\\\\w|&\[\]<>,\s]+)?\s*\{(.*?)\n\s*\}/s', $stripped, $m)) {
                    continue;
                }
                $afterBody = $m[1];
                if (preg_match('/\$this->error\s*\(/', $afterBody)) {
                    $locations[] = [
                        'file' => $file->getRealPath(),
                        'issue' => 'after() hook calls $this->error() (L10 API) instead of $validator->errors()->add()',
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
                message: 'No deprecated $this->error() usage in after() hooks.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' after() hook(s) use deprecated $this->error() API.',
            locations: $locations,
            suggestion: 'Use $validator->errors()->add(\'field\', \'message\') instead.',
        );
    }
}