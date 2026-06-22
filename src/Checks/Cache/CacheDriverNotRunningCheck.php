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

        // In-process / local drivers never need a remote service check.
        $local = ['array', 'file', 'null', 'database'];

        if (in_array($driver, $local, true)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: "Cache driver '{$driver}' is local and does not require a remote service.",
            );
        }

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
            // Real reachability check: ->getVersion() actually contacts the server.
            // The bare ->connect() call used previously could succeed even when
            // the server is unreachable.
            try {
                $memcached = app('memcached.connector')->connect(config('cache.stores.memcached.servers', []));
                $memcached->getVersion();
            } catch (\Throwable) {
                $locations[] = [
                    'issue' => 'Memcached cache driver configured but Memcached is not reachable',
                    'value' => "Cache driver: {$driver}",
                ];
            }
        } elseif ($driver === 'dynamodb') {
            // DynamoDB is a remote service; we cannot reliably ping it without
            // performing a real request, so we only verify the configuration
            // shape is present.
            $dynamodbConfig = config('cache.stores.dynamodb', []);
            if (empty($dynamodbConfig['key']) || empty($dynamodbConfig['secret']) || empty($dynamodbConfig['table'])) {
                $locations[] = [
                    'issue' => 'DynamoDB cache driver is missing required key/secret/table configuration',
                    'value' => "Cache driver: {$driver}",
                ];
            }
        } else {
            // Custom / unknown driver: verify it can actually be resolved and
            // has a callable getStore implementation.
            try {
                $store = cache()->getStore();
                if ($store === null) {
                    $locations[] = [
                        'issue' => "Custom cache driver '{$driver}' could not be resolved",
                        'value' => "Cache driver: {$driver}",
                    ];
                }
            } catch (\Throwable $e) {
                $locations[] = [
                    'issue' => "Custom cache driver '{$driver}' is not reachable: ".$e->getMessage(),
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
