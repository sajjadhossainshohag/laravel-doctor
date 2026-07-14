<?php

namespace SajjadHossain\Doctor;

use Illuminate\Support\ServiceProvider;
use SajjadHossain\Doctor\Checks\Blade\MissingNamedRoutesCheck;
use SajjadHossain\Doctor\Checks\Cache\SessionDriverMismatchCheck;
use SajjadHossain\Doctor\Checks\Components\AnonymousComponentCheck;
use SajjadHossain\Doctor\Checks\Components\ComponentClassCheck;
use SajjadHossain\Doctor\Checks\Components\ComponentNamespaceCheck;
use SajjadHossain\Doctor\Checks\Config\AbortIfWrongHttpCodeCheck;
use SajjadHossain\Doctor\Checks\Config\EarlyConfigAccessCheck;
use SajjadHossain\Doctor\Checks\Container\InterfaceBoundToDeletedConcreteCheck;
use SajjadHossain\Doctor\Checks\Container\SingletonAfterFirstResolveCheck;
use SajjadHossain\Doctor\Checks\Eloquent\AccessorMutatorStyleConflictCheck;
use SajjadHossain\Doctor\Checks\Eloquent\MissingGuardedOrFillableCheck;
use SajjadHossain\Doctor\Checks\Eloquent\ValueVsFirstOnNullCheck;
use SajjadHossain\Doctor\Checks\Eloquent\WithCountOnUndefinedRelationshipCheck;
use SajjadHossain\Doctor\Checks\Events\ListenerMissingHandleMethodCheck;
use SajjadHossain\Doctor\Checks\Events\MissingListenerClassCheck;
use SajjadHossain\Doctor\Checks\Gates\MissingPolicyClassCheck;
use SajjadHossain\Doctor\Checks\Jobs\BusChainCheck;
use SajjadHossain\Doctor\Checks\Jobs\FailedJobTableMissingCheck;
use SajjadHossain\Doctor\Checks\Jobs\JobDependencyResolutionCheck;
use SajjadHossain\Doctor\Checks\Jobs\JobHasHandleMethodCheck;
use SajjadHossain\Doctor\Checks\Jobs\JobTriesZeroCheck;
use SajjadHossain\Doctor\Checks\Jobs\MissingJobClassCheck;
use SajjadHossain\Doctor\Checks\Livewire\MissingLivewireComponentCheck;
use SajjadHossain\Doctor\Checks\Mail\MailableMissingViewCheck;
use SajjadHossain\Doctor\Checks\Mail\MailableVariableMismatchCheck;
use SajjadHossain\Doctor\Checks\Middleware\TerminateMethodThrowsCheck;
use SajjadHossain\Doctor\Checks\Middleware\UnregisteredMiddlewareCheck;
use SajjadHossain\Doctor\Checks\Routes\DuplicateRouteNamesCheck;
use SajjadHossain\Doctor\Checks\Routes\DuplicateUrisCheck;
use SajjadHossain\Doctor\Checks\Routes\InvalidMiddlewareCheck;
use SajjadHossain\Doctor\Checks\Routes\MissingControllerCheck;
use SajjadHossain\Doctor\Checks\Routes\MissingControllerMethodCheck;
use SajjadHossain\Doctor\Checks\Schedule\DeletedScheduledCommandCheck;
use SajjadHossain\Doctor\Checks\Schedule\OverlappingJobsWithoutLockCheck;
use SajjadHossain\Doctor\Checks\Schedule\ScheduledCommandNotExistsCheck;
use SajjadHossain\Doctor\Checks\Schema\ColumnMismatchCheck;
use SajjadHossain\Doctor\Checks\Schema\InvalidCastsCheck;
use SajjadHossain\Doctor\Checks\Storage\MissingStorageSymlinkCheck;
use SajjadHossain\Doctor\Checks\Storage\S3UrlWithoutConfigCheck;
use SajjadHossain\Doctor\Checks\Storage\StoreAsPathTraversalCheck;
use SajjadHossain\Doctor\Checks\Storage\UndefinedDiskCheck;
use SajjadHossain\Doctor\Checks\Validation\AuthorizeAlwaysFalseCheck;
use SajjadHossain\Doctor\Checks\Validation\NonExistentRuleClassCheck;
use SajjadHossain\Doctor\Checks\Views\MissingComponentCheck;
use SajjadHossain\Doctor\Checks\Views\MissingExtendsCheck;
use SajjadHossain\Doctor\Checks\Views\MissingIncludeCheck;
use SajjadHossain\Doctor\Checks\Views\StackPushMismatchCheck;
use SajjadHossain\Doctor\Commands\CacheClearCommand;
use SajjadHossain\Doctor\Commands\ScanCommand;

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
            $registry = $this->app->make(CheckRegistry::class);
            // Eloquent
            $registry->register(WithCountOnUndefinedRelationshipCheck::class);
            $registry->register(ValueVsFirstOnNullCheck::class);
            $registry->register(MissingGuardedOrFillableCheck::class);
            $registry->register(AccessorMutatorStyleConflictCheck::class);

            // Container
            $registry->register(InterfaceBoundToDeletedConcreteCheck::class);
            $registry->register(SingletonAfterFirstResolveCheck::class);

            // Events
            $registry->register(MissingListenerClassCheck::class);
            $registry->register(ListenerMissingHandleMethodCheck::class);

            // Mail
            $registry->register(MailableMissingViewCheck::class);
            $registry->register(MailableVariableMismatchCheck::class);

            // Middleware
            $registry->register(UnregisteredMiddlewareCheck::class);
            $registry->register(TerminateMethodThrowsCheck::class);

            // Validation
            $registry->register(AuthorizeAlwaysFalseCheck::class);
            $registry->register(NonExistentRuleClassCheck::class);

            // Storage
            $registry->register(S3UrlWithoutConfigCheck::class);
            $registry->register(UndefinedDiskCheck::class);
            $registry->register(StoreAsPathTraversalCheck::class);
            $registry->register(MissingStorageSymlinkCheck::class);

            // Cache
            $registry->register(SessionDriverMismatchCheck::class);

            // Schedule
            $registry->register(ScheduledCommandNotExistsCheck::class);
            $registry->register(DeletedScheduledCommandCheck::class);
            $registry->register(OverlappingJobsWithoutLockCheck::class);

            // Gates
            $registry->register(MissingPolicyClassCheck::class);

            // Livewire
            $registry->register(MissingLivewireComponentCheck::class);

            // Queue / Jobs
            $registry->register(FailedJobTableMissingCheck::class);
            $registry->register(JobTriesZeroCheck::class);
            $registry->register(BusChainCheck::class);
            $registry->register(JobDependencyResolutionCheck::class);
            $registry->register(JobHasHandleMethodCheck::class);
            $registry->register(MissingJobClassCheck::class);

            // Config
            $registry->register(EarlyConfigAccessCheck::class);
            $registry->register(AbortIfWrongHttpCodeCheck::class);

            // Blade
            $registry->register(MissingNamedRoutesCheck::class);

            // Components
            $registry->register(AnonymousComponentCheck::class);
            $registry->register(ComponentClassCheck::class);
            $registry->register(ComponentNamespaceCheck::class);

            // Routes
            $registry->register(DuplicateRouteNamesCheck::class);
            $registry->register(DuplicateUrisCheck::class);
            $registry->register(InvalidMiddlewareCheck::class);
            $registry->register(MissingControllerCheck::class);
            $registry->register(MissingControllerMethodCheck::class);

            // Schema
            $registry->register(ColumnMismatchCheck::class);
            $registry->register(InvalidCastsCheck::class);

            // Views
            $registry->register(MissingComponentCheck::class);
            $registry->register(MissingExtendsCheck::class);
            $registry->register(MissingIncludeCheck::class);
            $registry->register(StackPushMismatchCheck::class);

            $this->commands([
                ScanCommand::class,
                CacheClearCommand::class,
            ]);
        }
    }
}
