<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Providers;

use Illuminate\Support\ServiceProvider;

class SingletonInBootProvider extends ServiceProvider
{
    public function register(): void
    {
        // Resolving the same abstract in register() that boot() later
        // re-binds as a singleton() — that's the bug we want to detect.
        $this->app->make('test.service');
    }

    public function boot(): void
    {
        $this->app->singleton('test.service', fn () => new \stdClass());
    }
}
