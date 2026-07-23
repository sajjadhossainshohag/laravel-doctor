<?php

namespace SajjadHossain\Doctor;

use Illuminate\Support\ServiceProvider;
use SajjadHossain\Doctor\Checks\Blade\MissingNamedRoutesCheck;
use SajjadHossain\Doctor\Checks\Cache\SessionDriverMismatchCheck;
use SajjadHossain\Doctor\Checks\Components\{
    AnonymousComponentCheck,
    ComponentClassCheck,
    ComponentNamespaceCheck,
};
use SajjadHossain\Doctor\Checks\Config\{
    AbortIfWrongHttpCodeCheck,
    EarlyConfigAccessCheck,
    NonExistentConfigFileCheck,
    NonExistentConfigKeyCheck,
};
use SajjadHossain\Doctor\Checks\Container\{
    InterfaceBoundToDeletedConcreteCheck,
    SingletonAfterFirstResolveCheck,
};
use SajjadHossain\Doctor\Checks\Debug\DebugStatementLeftInCheck;
use SajjadHossain\Doctor\Checks\Eloquent\{
    AccessorMutatorStyleConflictCheck,
    GetThenCountCheck,
    MissingGuardedOrFillableCheck,
    ValueVsFirstOnNullCheck,
    WithCountOnUndefinedRelationshipCheck,
};
use SajjadHossain\Doctor\Checks\Env\{
    AppKeyCheck,
    EnvExampleMismatchCheck,
    MissingEnvKeysCheck,
};
use SajjadHossain\Doctor\Checks\Events\{
    ListenerMissingHandleMethodCheck,
    MissingListenerClassCheck,
};
use SajjadHossain\Doctor\Checks\Gates\MissingPolicyClassCheck;
use SajjadHossain\Doctor\Checks\Jobs\{
    BusChainCheck,
    FailedJobTableMissingCheck,
    JobDependencyResolutionCheck,
    JobHasHandleMethodCheck,
    JobTriesZeroCheck,
    MissingJobClassCheck,
};
use SajjadHossain\Doctor\Checks\Livewire\MissingLivewireComponentCheck;
use SajjadHossain\Doctor\Checks\Mail\{
    MailableMissingViewCheck,
    MailableVariableMismatchCheck,
};
use SajjadHossain\Doctor\Checks\Middleware\{
    TerminateMethodThrowsCheck,
    UnregisteredMiddlewareCheck,
};
use SajjadHossain\Doctor\Checks\Routes\{
    DuplicateRouteNamesCheck,
    DuplicateUrisCheck,
    InvalidMiddlewareCheck,
    MissingControllerCheck,
    MissingControllerMethodCheck,
    RouteClosureBreaksCacheCheck,
};
use SajjadHossain\Doctor\Checks\Schedule\{
    DeletedScheduledCommandCheck,
    OverlappingJobsWithoutLockCheck,
    ScheduledCommandNotExistsCheck,
};
use SajjadHossain\Doctor\Checks\Schema\{
    ColumnMismatchCheck,
    InvalidCastsCheck,
};
use SajjadHossain\Doctor\Checks\Security\RequestAllInCreateCheck;
use SajjadHossain\Doctor\Checks\Storage\{
    MissingStorageSymlinkCheck,
    S3UrlWithoutConfigCheck,
    StoreAsPathTraversalCheck,
    UndefinedDiskCheck,
};
use SajjadHossain\Doctor\Checks\Validation\{
    AuthorizeAlwaysFalseCheck,
    NonExistentRuleClassCheck,
};
use SajjadHossain\Doctor\Checks\Views\{
    MissingComponentCheck,
    MissingExtendsCheck,
    MissingIncludeCheck,
    StackPushMismatchCheck,
};
use SajjadHossain\Doctor\Commands\CacheClearCommand;
use SajjadHossain\Doctor\Commands\ScanCommand;
use SajjadHossain\Doctor\Commands\WorkerCommand;

class DoctorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->app->singleton(CheckRegistry::class);
            $this->app->singleton(ScanResultCache::class);
        }

        $this->mergeConfigFrom(__DIR__ . '/../config/doctor.php', 'doctor');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/doctor.php' => config_path('doctor.php'),
        ], 'doctor-config');

        if ($this->app->runningInConsole()) {
            $this->app->make(CheckRegistry::class)->registerMany([
                // Eloquent
                WithCountOnUndefinedRelationshipCheck::class,
                ValueVsFirstOnNullCheck::class,
                MissingGuardedOrFillableCheck::class,
                AccessorMutatorStyleConflictCheck::class,
                GetThenCountCheck::class,

                // Container
                InterfaceBoundToDeletedConcreteCheck::class,
                SingletonAfterFirstResolveCheck::class,

                // Env
                AppKeyCheck::class,
                EnvExampleMismatchCheck::class,
                MissingEnvKeysCheck::class,

                // Events
                MissingListenerClassCheck::class,
                ListenerMissingHandleMethodCheck::class,

                // Mail
                MailableMissingViewCheck::class,
                MailableVariableMismatchCheck::class,

                // Middleware
                UnregisteredMiddlewareCheck::class,
                TerminateMethodThrowsCheck::class,

                // Validation
                AuthorizeAlwaysFalseCheck::class,
                NonExistentRuleClassCheck::class,

                // Storage
                S3UrlWithoutConfigCheck::class,
                UndefinedDiskCheck::class,
                StoreAsPathTraversalCheck::class,
                MissingStorageSymlinkCheck::class,

                // Cache
                SessionDriverMismatchCheck::class,

                // Schedule
                ScheduledCommandNotExistsCheck::class,
                DeletedScheduledCommandCheck::class,
                OverlappingJobsWithoutLockCheck::class,

                // Gates
                MissingPolicyClassCheck::class,

                // Livewire
                MissingLivewireComponentCheck::class,

                // Queue / Jobs
                FailedJobTableMissingCheck::class,
                JobTriesZeroCheck::class,
                BusChainCheck::class,
                JobDependencyResolutionCheck::class,
                JobHasHandleMethodCheck::class,
                MissingJobClassCheck::class,

                // Config
                EarlyConfigAccessCheck::class,
                AbortIfWrongHttpCodeCheck::class,
                NonExistentConfigFileCheck::class,
                NonExistentConfigKeyCheck::class,

                // Blade
                MissingNamedRoutesCheck::class,

                // Components
                AnonymousComponentCheck::class,
                ComponentClassCheck::class,
                ComponentNamespaceCheck::class,

                // Routes
                DuplicateRouteNamesCheck::class,
                DuplicateUrisCheck::class,
                InvalidMiddlewareCheck::class,
                MissingControllerCheck::class,
                MissingControllerMethodCheck::class,
                RouteClosureBreaksCacheCheck::class,

                // Schema
                ColumnMismatchCheck::class,
                InvalidCastsCheck::class,

                // Views
                MissingComponentCheck::class,
                MissingExtendsCheck::class,
                MissingIncludeCheck::class,
                StackPushMismatchCheck::class,

                // Security
                RequestAllInCreateCheck::class,

                // Debug
                DebugStatementLeftInCheck::class,
            ]);

            $this->commands([
                ScanCommand::class,
                CacheClearCommand::class,
                WorkerCommand::class,
            ]);
        }
    }
}
