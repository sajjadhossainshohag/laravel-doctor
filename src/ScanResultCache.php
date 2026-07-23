<?php

namespace SajjadHossain\Doctor;

use Illuminate\Support\Facades\Cache;

class ScanResultCache
{
    private const CATEGORIES = [
        'blade', 'cache', 'components', 'config', 'container', 'debug',
        'eloquent', 'env', 'events', 'gates', 'jobs', 'livewire', 'mail',
        'middleware', 'routes', 'schedule', 'schema', 'security', 'storage',
        'validation', 'views',
    ];

    public function remember(string $category, callable $callback): array
    {
        if (!config('doctor.cache.enabled', true)) {
            return $callback();
        }

        $key = $this->key($category);
        $ttl = config('doctor.cache.ttl', 3600);
        $store = $this->store();

        return Cache::store($store)->remember($key, $ttl, $callback);
    }

    public function get(string $category): ?array
    {
        if (!config('doctor.cache.enabled', true)) {
            return null;
        }

        $result = Cache::store($this->store())->get($this->key($category));

        return is_array($result) ? $result : null;
    }

    public function put(string $category, array $results): void
    {
        if (!config('doctor.cache.enabled', true)) {
            return;
        }

        Cache::store($this->store())->put(
            $this->key($category),
            $results,
            config('doctor.cache.ttl', 3600),
        );
    }

    public function forget(string $category): void
    {
        Cache::store($this->store())->forget($this->key($category));
    }

    public function flush(): void
    {
        foreach (self::CATEGORIES as $category) {
            Cache::store($this->store())->forget($this->key($category));
        }
    }

    private function key(string $category): string
    {
        return "doctor_scan_{$category}";
    }

    private function store(): string
    {
        return config('doctor.cache.store', 'file');
    }
}
