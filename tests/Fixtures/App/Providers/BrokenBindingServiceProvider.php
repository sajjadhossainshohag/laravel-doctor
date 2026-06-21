<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Providers;

use Illuminate\Support\ServiceProvider;

class BrokenBindingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            \SajjadHossain\Doctor\Tests\Fixtures\App\Contracts\PaymentGateway::class,
            \SajjadHossain\Doctor\Tests\Fixtures\App\Services\DeletedPaymentGateway::class
        );
    }

    public function boot(): void
    {
        //
    }
}
