<?php

namespace SajjadHossain\Doctor\Checks\Jobs;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class BusChainCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Bus Chain Jobs';
    }

    public function category(): string
    {
        return 'jobs';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];

        $paths = config('doctor.scan_paths', [app_path()]);
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
                if (preg_match('/Bus::chain\(\[([^\]]+)\]\)/', $content, $match)) {
                    $chained = explode(',', $match[1]);
                    foreach ($chained as $entry) {
                        $entry = trim($entry);
                        $entry = preg_replace('/::class$/', '', $entry);
                        $entry = trim($entry, "'\"");
                        if (!empty($entry) && !class_exists($entry)) {
                            $locations[] = [
                                'file' => $file->getRealPath(),
                                'job' => $entry,
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
                message: 'All Bus::chain() job classes exist.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' chained job class(es) not found.',
            locations: $locations,
        );
    }
}
