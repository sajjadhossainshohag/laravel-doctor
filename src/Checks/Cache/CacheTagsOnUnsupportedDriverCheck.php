<?php

namespace SajjadHossain\Doctor\Checks\Cache;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class CacheTagsOnUnsupportedDriverCheck implements HealthCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Cache::tags() on Unsupported Driver';
    }

    public function category(): string
    {
        return 'cache';
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: config('doctor.scan_paths', [app_path(), resource_path('views')]);
        $driver = config('cache.default', 'file');

        // Drivers that natively support tags via a TaggableStore subclass.
        // ArrayStore, ApcStore, MemcachedStore, NullStore, RedisStore, FailoverStore
        // all extend TaggableStore, so they support ->tags().
        $supportsTags = in_array($driver, [
            'array', 'apc', 'memcached', 'redis', 'dynamodb',
        ], true);

        if ($supportsTags) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: "Cache driver '{$driver}' supports tags.",
            );
        }

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
                if (preg_match('/Cache::tags\s*\(/', $content)) {
                    $locations[] = [
                        'file' => $file->getRealPath(),
                        'issue' => "Cache::tags() called but driver '{$driver}' does not support tags",
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
                message: "No Cache::tags() calls on unsupported driver '{$driver}'.",
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' Cache::tags() call(s) on driver that does not support tags.',
            locations: $locations,
            suggestion: 'Switch to a taggable driver (redis, memcached, array, apc, dynamodb) or remove tags() calls.',
        );
    }
}
