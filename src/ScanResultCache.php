<?php

namespace SajjadHossain\Doctor;

use Illuminate\Support\Facades\Cache;

class ScanResultCache
{
    public function remember(string $category, callable $callback): array
    {
        if (!config('doctor.cache.enabled', true)) {
            return $callback();
        }

        $key = $this->key($category);
        $ttl = config('doctor.cache.ttl', 3600);
        $store = config('doctor.cache.store', 'file');

        return Cache::store($store)->remember($key, $ttl, $callback);
    }

    public function forget(string $category): void
    {
        Cache::forget($this->key($category));
    }

    public function flush(): void
    {
        $store = config('doctor.cache.store', 'file');
        Cache::store($store)->flush();
    }

    private function key(string $category): string
    {
        return "doctor_scan_{$category}";
    }
}
