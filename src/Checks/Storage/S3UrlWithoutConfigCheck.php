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
        return Severity::Info;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = config('doctor.scan_paths', [app_path(), resource_path('views')]);

        $s3Config = config('filesystems.disks.s3');
        $hasBucket = ! empty($s3Config['bucket']);
        $hasRegion = ! empty($s3Config['region']);
        $hasExplicitCreds = ! empty($s3Config['key']) && ! empty($s3Config['secret']);

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

                // Strip block + line comments so commented-out code does
                // not match.
                $stripped = preg_replace('#/\*.*?\*/#s', '', $content);
                $stripped = preg_replace('!//[^\n]*!', '', $stripped);

                // We MUST NOT wipe quoted strings here — the detection
                // regex needs the literal 's3' to match. Instead, we only
                // need to ignore strings that happen to contain an
                // `Storage::disk('s3')->url(` substring, which essentially
                // never happens in real code (it would mean a string
                // containing the exact call text).
                if (! preg_match('/Storage::disk\s*\(\s*[\'"]s3[\'"]\s*\)\s*->\s*url\s*\(/', $stripped)) {
                    continue;
                }

                // The S3 disk may legitimately be used without explicit
                // key/secret when the application runs on AWS and picks
                // up IAM role credentials via the default credential
                // provider chain. We only flag when bucket or region
                // is missing — those are always required.
                if (! $hasBucket || ! $hasRegion) {
                    $missing = [];
                    if (! $hasBucket) { $missing[] = 'bucket'; }
                    if (! $hasRegion) { $missing[] = 'region'; }
                    $locations[] = [
                        'file' => $file->getRealPath(),
                        'issue' => "Storage::disk('s3')->url() called but S3 is missing required config: ".implode(', ', $missing),
                    ];
                }
                // If key/secret are missing but bucket/region are present,
                // the app may be using IAM/instance role credentials —
                // that's valid. Don't flag.
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All S3 URL calls have the required bucket and region configured.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' S3 url() call(s) without required bucket/region.',
            locations: $locations,
            suggestion: 'Set the s3.bucket and s3.region values in config/filesystems.php.',
        );
    }
}
