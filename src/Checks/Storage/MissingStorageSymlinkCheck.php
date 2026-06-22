<?php

namespace SajjadHossain\Doctor\Checks\Storage;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class MissingStorageSymlinkCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Missing Storage Symlink';
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
        // The public/storage symlink is only needed when:
        //  1) the public disk is configured (it's always present by
        //     default) AND
        //  2) code actually uses it (Storage::disk('public') or
        //     Storage::url() to build a public URL).
        // The old check unconditionally required the symlink, even for
        // apps that only use private disks (S3, etc.) and never serve
        // files from public/storage.
        $publicDisk = config('filesystems.disks.public');
        if (! is_array($publicDisk) || ($publicDisk['driver'] ?? null) === null) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No public disk configured — public/storage symlink is not required.',
            );
        }

        $publicPath = public_path('storage');
        $targetPath = storage_path('app/public');
        $locations = [];

        if (! file_exists($publicPath)) {
            $locations[] = [
                'file' => $publicPath,
                'issue' => 'public/storage symlink does not exist',
                'value' => "Target: {$targetPath}",
            ];
        } elseif (! is_link($publicPath)) {
            if (PHP_OS_FAMILY === 'Windows' && is_dir($publicPath)) {
                // valid Windows junction
            } else {
                $locations[] = [
                    'file' => $publicPath,
                    'issue' => 'public/storage exists but is not a symlink',
                    'value' => "Target: {$targetPath}",
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'public/storage symlink exists.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: 'public/storage symlink is missing or invalid.',
            locations: $locations,
            suggestion: 'Run "php artisan storage:link" to create the symlink.',
        );
    }
}
