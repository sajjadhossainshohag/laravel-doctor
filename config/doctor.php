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
    | Ignored Patterns & Allowlisted Environment Keys
    |--------------------------------------------------------------------------
    |
    | Ignored Patterns: Glob patterns per category to exclude from scans.
    | Use these to silence noise from third-party packages, vendor views,
    | or any pattern you don't want Doctor to analyze.
    |
    | Allowlisted Env Keys: Environment variables referenced in config files
    | without a default value that should NOT be flagged as missing. These
    | are typically optional service credentials in stock Laravel config.
    |
    | Supported pattern syntax: * matches anything except /, ** matches
    | anything including /, ? matches a single character.
    |
    */

    'allowlisted_env_keys' => [
        // Session (optional driver-specific settings)
        'SESSION_CONNECTION',
        'SESSION_STORE',
        'SESSION_DOMAIN',
        'SESSION_SECURE_COOKIE',

        // Cache (per-driver overrides)
        'DB_CACHE_CONNECTION',
        'DB_CACHE_LOCK_CONNECTION',
        'DB_CACHE_LOCK_TABLE',
        'CACHE_STORAGE_DISK',
        'CACHE_STORAGE_PATH',
        'MEMCACHED_PERSISTENT_ID',
        'MEMCACHED_USERNAME',
        'MEMCACHED_PASSWORD',
        'DYNAMODB_ENDPOINT',

        // Database (per-connection overrides)
        'DB_URL',
        'MYSQL_ATTR_SSL_CA',
        'REDIS_URL',
        'REDIS_USERNAME',
        'REDIS_PASSWORD',
        'REDIS_MAX_RETRIES',
        'REDIS_BACKOFF_ALGORITHM',
        'REDIS_BACKOFF_BASE',
        'REDIS_BACKOFF_CAP',
        'REDIS_CLUSTER',
        'REDIS_PREFIX',
        'REDIS_PERSISTENT',

        // Filesystem (S3-specific)
        'AWS_ACCESS_KEY_ID',
        'AWS_SECRET_ACCESS_KEY',
        'AWS_DEFAULT_REGION',
        'AWS_BUCKET',
        'AWS_URL',
        'AWS_ENDPOINT',

        // Logging (per-channel config)
        'LOG_SLACK_WEBHOOK_URL',
        'LOG_STDERR_FORMATTER',
        'PAPERTRAIL_URL',
        'PAPERTRAIL_PORT',

        // Mail (per-mailer config)
        'MAIL_SCHEME',
        'MAIL_URL',
        'MAIL_USERNAME',
        'MAIL_PASSWORD',
        'MAIL_EHLO_DOMAIN',
        'MAIL_LOG_CHANNEL',

        // Queue (per-connection config)
        'DB_QUEUE_CONNECTION',
        'SQS_SUFFIX',

        // Third-party service credentials
        'POSTMARK_API_KEY',
        'RESEND_API_KEY',
        'SLACK_BOT_USER_OAUTH_TOKEN',
        'SLACK_BOT_USER_DEFAULT_CHANNEL',

        // Auth
        'AUTH_MODEL',
    ],

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
        'debug'      => ['vendor/*'],
        'security'   => ['vendor/*'],
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
            'debug'      => 3,
            'security'   => 10,
        ],
    ],

];
