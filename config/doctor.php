<?php

return [

    'enabled' => env('DOCTOR_ENABLED', true),

    'scan_paths' => [
        app_path(),
        resource_path('views'),
    ],

    'ignore' => [
        'routes' => ['telescope.*', 'debugbar.*', 'horizon.*'],
        'views' => ['vendor/*'],
        'components' => ['vendor/*'],
        'eloquent' => ['vendor/*', 'migrations/*'],
        'container' => ['vendor/*'],
        'events' => ['vendor/*'],
        'mail' => ['vendor/*'],
        'middleware' => ['vendor/*'],
        'validation' => ['vendor/*'],
        'storage' => ['vendor/*'],
        'cache' => ['vendor/*'],
        'schedule' => ['vendor/*'],
        'gates' => ['vendor/*'],
        'livewire' => ['vendor/*'],
        'config' => ['vendor/*'],
    ],

    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'store' => env('DOCTOR_CACHE_STORE', 'file'),
    ],

    'health_score' => [
        'weights' => [
            'schema' => 12,
            'eloquent' => 12,
            'routes' => 10,
            'views' => 8,
            'components' => 5,
            'jobs' => 5,
            'cache' => 5,
            'storage' => 5,
            'validation' => 5,
            'container' => 5,
            'events' => 4,
            'mail' => 4,
            'middleware' => 4,
            'schedule' => 4,
            'gates' => 3,
            'livewire' => 3,
            'config' => 2,
        ],
    ],

    'notifications' => [
        'slack_webhook' => env('DOCTOR_SLACK_WEBHOOK'),
        'email' => env('DOCTOR_NOTIFY_EMAIL'),
    ],

];
