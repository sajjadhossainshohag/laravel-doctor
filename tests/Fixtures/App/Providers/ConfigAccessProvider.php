<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Providers;

use Illuminate\Support\ServiceProvider;

class ConfigAccessProvider extends ServiceProvider
{
    public function register()
    {
        $value = config('app.name');
    }

    public function boot(): void {}
}
