<?php

namespace SajjadHossain\Doctor\Checks\Cache;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class CacheDriverNotRunningCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Cache Driver Service Not Running';
    }

    public function category(): string
    {
        return 'cache';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $driver = config('cache.default', 'file');
        $locations = [];

        if ($driver === 'redis') {
            try {
                app('redis')->ping();
            } catch (\Throwable) {
                $locations[] = [
                    'issue' => 'Redis cache driver configured but Redis is not reachable',
                    'value' => "Cache driver: {$driver}",
                ];
            }
        } elseif ($driver === 'memcached') {
            try {
                app('memcached.connector')->connect(config('cache.stores.memcached.servers', []));
            } catch (\Throwable) {
                $locations[] = [
                    'issue' => 'Memcached cache driver configured but Memcached is not reachable',
                    'value' => "Cache driver: {$driver}",
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: "Cache driver '{$driver}' appears reachable.",
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: 'Cache driver service may not be running.',
            locations: $locations,
            suggestion: 'Start the cache service or switch to a different cache driver.',
        );
    }
}
