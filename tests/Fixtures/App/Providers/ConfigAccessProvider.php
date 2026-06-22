<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Providers;

use Illuminate\Support\ServiceProvider;

class ConfigAccessProvider extends ServiceProvider
{
    public function register(): void
    {
        $value = env('APP_ENV');
    }

    public function boot(): void {}
}
