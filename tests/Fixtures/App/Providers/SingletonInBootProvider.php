<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Providers;

use Illuminate\Support\ServiceProvider;

class SingletonInBootProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->app->singleton('test.service', fn () => new \stdClass());
    }
}
