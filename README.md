# Laravel Doctor

**Code Health Checker for Laravel** — catch broken routes, missing views, schema mismatches, and runtime errors before deployment.

> **Beta** — under active development. Things may change.

Run a single artisan command to scan your entire Laravel codebase for 50+ common issues, grouped by category with severity levels.

---

## Requirements

- PHP ^8.2
- Laravel ^11.0 | ^12.0 | ^13.0

Publish the config (optional):

```bash
php artisan vendor:publish --tag=doctor-config
```

## Usage

```bash
php artisan doctor:scan
```

### Options

| Option | Description |
|---|---|
| `--only=routes,views,env` | Comma-separated categories to scan |
| `--json` | Output as JSON |
| `--html` | Output as HTML |
| `--fail-on=error,warning` | Exit code 1 if issues at these severities exist |
| `--no-cache` | Skip cached results |
| `--verbose` | More detailed console output |
| `--help` | Display help |

### Examples

```bash
# Scan everything
php artisan doctor:scan

# Only check routes and views
php artisan doctor:scan --only=routes,views

# Fail CI pipeline on any error or warning
php artisan doctor:scan --fail-on=error,warning

# JSON output for tooling
php artisan doctor:scan --json
```

### Cache

Results are cached (default 3600s). Clear with:

```bash
php artisan doctor:cache:clear
```

---

## What It Catches

### Routes
- **MissingControllerCheck** — route references a controller class that doesn't exist
- **MissingControllerMethodCheck** — route references a method that doesn't exist on the controller
- **DuplicateRouteNamesCheck** — two or more routes share the same `->name()`
- **DuplicateUrisCheck** — two or more routes share the same URI + HTTP method
- **InvalidMiddlewareCheck** — route references middleware that is not registered

### Views
- **MissingIncludeCheck** — `@include('view')` references a view that doesn't exist
- **MissingExtendsCheck** — `@extends('layout')` references a layout that doesn't exist
- **MissingComponentCheck** — `@component('name')` references a component that doesn't exist
- **StackPushMismatchCheck** — `@push('name')` exists but no corresponding `@stack('name')`

### Blade
- **MissingNamedRoutesCheck** — Blade templates calling `route('name')` where the route is undefined, or using `url('name')` where `route()` should be used

### Components
- **ComponentClassCheck** — Blade component alias references a class that doesn't exist
- **ComponentNamespaceCheck** — view namespace maps to a non-existent directory
- **AnonymousComponentCheck** — anonymous component namespace maps to a non-existent directory

### Eloquent / Models
- **WithCountOnUndefinedRelationshipCheck** — `->withCount('rel')` where the relationship method doesn't exist
- **ValueVsFirstOnNullCheck** — `->first()->property` without a null guard (crashes on empty result)
- **MissingGuardedOrFillableCheck** — model has neither `$fillable` nor `$guarded` (mass-assignment unprotected)
- **AccessorMutatorStyleConflictCheck** — model mixes old-style accessors with new `Attribute::make()` pattern

### Schema / Database
- **ColumnMismatchCheck** — `$fillable` or `$casts` columns don't exist in the actual database table
- **InvalidCastsCheck** — model `$casts` references invalid cast types or classes

### Cache
- **SessionDriverMismatchCheck** — session driver is `database` but the sessions table doesn't exist

### Config
- **EarlyConfigAccessCheck** — `config()` called inside a service provider's `register()` method
- **AbortIfWrongHttpCodeCheck** — `abort_if()` / `abort_unless()` called with a code below 400

### Env
- **AppKeyCheck** — `APP_KEY` is empty, placeholder, or incomplete
- **MissingEnvKeysCheck** — `env('KEY')` references a key not defined in `.env`
- **EnvExampleMismatchCheck** — `.env` has keys missing from `.env.example`

### Jobs / Queue
- **MissingJobClassCheck** — `Job::dispatch()` references a class that doesn't exist
- **BusChainCheck** — `Bus::chain()` references a job class that doesn't exist
- **JobHasHandleMethodCheck** — `ShouldQueue` class has no `handle()` method
- **JobDependencyResolutionCheck** — job constructor has unresolvable type-hinted parameter
- **JobTriesZeroCheck** — job has `public $tries = 0` (never retries)
- **FailedJobTableMissingCheck** — `failed_jobs` table doesn't exist

### Events
- **MissingListenerClassCheck** — event listener class doesn't exist
- **ListenerMissingHandleMethodCheck** — listener has no `handle()` method

### Middleware
- **UnregisteredMiddlewareCheck** — `->middleware('alias')` used but alias not registered
- **TerminateMethodThrowsCheck** — `terminate()` makes external calls without try/catch

### Validation
- **NonExistentRuleClassCheck** — custom rule class instantiated but doesn't exist
- **AuthorizeAlwaysFalseCheck** — FormRequest `authorize()` hardcoded to `return false`

### Storage
- **UndefinedDiskCheck** — `Storage::disk('name')` references an undefined disk
- **StoreAsPathTraversalCheck** — `->storeAs()` path contains `..` (path traversal risk)
- **S3UrlWithoutConfigCheck** — S3 `url()` called without full configuration
- **MissingStorageSymlinkCheck** — `public/storage` symlink doesn't exist

### Container
- **SingletonAfterFirstResolveCheck** — singleton registered in `boot()` instead of `register()`
- **InterfaceBoundToDeletedConcreteCheck** — container binding references a deleted concrete class

### Schedule
- **ScheduledCommandNotExistsCheck** — `$schedule->command(Class::class)` references a non-existent class
- **DeletedScheduledCommandCheck** — `$schedule->command('name')` references an unregistered command
- **OverlappingJobsWithoutLockCheck** — frequent task doesn't use `->withoutOverlapping()`

### Gates
- **MissingPolicyClassCheck** — `Gate::policy()` references a policy class that doesn't exist

### Livewire
- **MissingLivewireComponentCheck** — `<livewire:name>` used but component class doesn't exist

### Mail
- **MailableMissingViewCheck** — mailable's `->view('name')` references a view that doesn't exist
- **MailableVariableMismatchCheck** — mailable passes variables to a template that doesn't use them

---

## Configuration

```php
// config/doctor.php
return [
    'cache' => [
        'enabled' => true,     // cache scan results
        'ttl'     => 3600,     // seconds
        'store'   => 'file',   // cache store
    ],
    'scan_paths' => [
        app_path(),
        resource_path('views'),
    ],
    'ignore' => [
        'container' => [],     // file patterns to ignore for container checks
    ],
];
```

---

## License

MIT
