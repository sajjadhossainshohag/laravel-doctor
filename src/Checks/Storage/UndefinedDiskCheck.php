<?php

namespace SajjadHossain\Doctor\Checks\Storage;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class UndefinedDiskCheck implements HealthCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Storage::disk() References Undefined Disk';
    }

    public function category(): string
    {
        return 'storage';
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: config('doctor.scan_paths', [app_path(), resource_path('views')]);
        $disks = array_keys(config('filesystems.disks', []));

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

                // preg_match_all so EVERY Storage::disk() call per file
                // is checked. The old check used preg_match which only
                // inspected the first occurrence.
                if (preg_match_all('/Storage::disk\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $stripped, $m)) {
                    foreach ($m[1] as $disk) {
                        if (! in_array($disk, $disks, true)) {
                            $locations[] = [
                                'file' => $file->getRealPath(),
                                'issue' => "Storage::disk('{$disk}') — '{$disk}' is not defined in config/filesystems.php",
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
                message: 'All Storage::disk() calls reference defined disks.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' Storage::disk() call(s) reference undefined disk(s).',
            locations: $locations,
            suggestion: 'Define the disk in config/filesystems.php or fix the disk name.',
        );
    }
}
