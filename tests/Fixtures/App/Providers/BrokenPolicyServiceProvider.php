<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class BrokenPolicyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(
            \SajjadHossain\Doctor\Tests\Fixtures\App\Models\Good\WellDefinedModel::class,
            \SajjadHossain\Doctor\Tests\Fixtures\App\Policies\NonExistentPolicy::class
        );
    }
}
