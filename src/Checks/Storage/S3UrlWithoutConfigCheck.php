<?php

namespace SajjadHossain\Doctor\Checks\Storage;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class S3UrlWithoutConfigCheck implements HealthCheck
{
    public function name(): string
    {
        return 'S3 URL Called Without Config';
    }

    public function category(): string
    {
        return 'storage';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = config('doctor.scan_paths', [app_path(), resource_path('views')]);

        $s3Config = config('filesystems.disks.s3');
        $s3Configured = ! empty($s3Config['key']) && ! empty($s3Config['secret']) && ! empty($s3Config['bucket']);

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
                if (preg_match('/Storage::disk\s*\(\s*[\'"]s3[\'"]\s*\)\s*->\s*url\s*\(/', $content)) {
                    if (! $s3Configured) {
                        $locations[] = [
                            'file' => $file->getRealPath(),
                            'issue' => 'Storage::disk(\'s3\')->url() called but S3 is not fully configured',
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
                message: 'No S3 URL calls without configuration detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' S3 url() call(s) with incomplete S3 configuration.',
            locations: $locations,
            suggestion: 'Configure the S3 disk in config/filesystems.php or use a different disk.',
        );
    }
}
