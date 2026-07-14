<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Providers;

use Illuminate\Support\ServiceProvider;

class BrokenListenersProvider extends ServiceProvider
{
    protected $listen = [
        \SajjadHossain\Doctor\Tests\Fixtures\App\Events\OrderShipped::class => [
            \SajjadHossain\Doctor\Tests\Fixtures\App\Listeners\DeletedListener::class,
            \SajjadHossain\Doctor\Tests\Fixtures\App\Listeners\SendShipmentNotification::class,
        ],
    ];

    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::listen(
            \SajjadHossain\Doctor\Tests\Fixtures\App\Listeners\NonExistentListener::class,
            'handle'
        );
    }
}
