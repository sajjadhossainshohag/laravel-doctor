<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Doctor Enabled
    |--------------------------------------------------------------------------
    |
    | This option controls whether Doctor runs at all. When set to false, all
    | health checks are skipped globally. Useful for environments where you
    | don't want to run scans (e.g. production).
    |
    */

    'enabled' => env('DOCTOR_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Scan Paths
    |--------------------------------------------------------------------------
    |
    | Directories that Doctor will recursively scan for PHP and Blade files.
    | Add paths for custom namespaces, package directories, or any location
    | containing code you want analyzed.
    |
    */

    'scan_paths' => [
        app_path(),
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored Patterns
    |--------------------------------------------------------------------------
    |
    | Glob patterns per category to exclude from scans. Use these to silence
    | noise from third-party packages, vendor-published views, or any file
    | pattern you don't want Doctor to analyze.
    |
    | Supported pattern syntax: * matches anything except /, ** matches
    | anything including /, ? matches a single character.
    |
    */

    'ignore' => [
        'routes'     => ['telescope.*', 'debugbar.*', 'horizon.*'],
        'views'      => ['vendor/*'],
        'components' => ['vendor/*'],
        'eloquent'   => ['vendor/*', 'migrations/*'],
        'container'  => ['vendor/*'],
        'events'     => ['vendor/*'],
        'mail'       => ['vendor/*'],
        'middleware' => ['vendor/*'],
        'validation' => ['vendor/*'],
        'storage'    => ['vendor/*'],
        'cache'      => ['vendor/*'],
        'schedule'   => ['vendor/*'],
        'gates'      => ['vendor/*'],
        'livewire'   => ['vendor/*'],
        'config'     => ['vendor/*'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Doctor can cache scan results to avoid re-analyzing unchanged code.
    | Set enabled to false to always re-scan, or use --no-cache at runtime.
    |
    | The store option controls which Laravel cache driver Doctor uses.
    | Supported stores: "file", "redis", "memcached", "array", "database".
    |
    */

    'cache' => [
        'enabled' => true,
        'ttl'     => 3600,
        'store'   => env('DOCTOR_CACHE_STORE', 'file'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Score Weights
    |--------------------------------------------------------------------------
    |
    | The relative weight of each category when calculating an overall code
    | health score. Higher numbers mean that category contributes more to
    | the final score. Adjust these to match your team's priorities.
    |
    */

    'health_score' => [
        'weights' => [
            'schema'     => 12,
            'eloquent'   => 12,
            'routes'     => 10,
            'views'      => 8,
            'components' => 5,
            'jobs'       => 5,
            'cache'      => 5,
            'storage'    => 5,
            'validation' => 5,
            'container'  => 5,
            'events'     => 4,
            'mail'       => 4,
            'middleware' => 4,
            'schedule'   => 4,
            'gates'      => 3,
            'livewire'   => 3,
            'config'     => 2,
        ],
    ],

];
