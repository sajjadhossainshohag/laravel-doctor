---
name: laravel-doctor
description: Use Laravel Doctor to scan a Laravel project for code health issues — broken routes, missing views, schema mismatches, invalid middleware, and other static-analysis problems. Use when running doctor:scan, interpreting scan output, fixing issues Doctor reports, or ensuring new code passes all checks.
---

# Laravel Doctor

## When to use this skill

Use this skill when running `php artisan doctor:scan`, debugging a failing check, or writing new code that must pass Laravel Doctor's static analysis. Always run the scan after generating routes, views, middleware, controllers, jobs, events, mailables, or Eloquent models to catch issues before deployment.

## Running the scan

```
php artisan doctor:scan
```

Common options:

```
php artisan doctor:scan --only=routes,views              # Run specific categories only
php artisan doctor:scan --fail-on=error                  # Exit code 1 on errors
php artisan doctor:scan --json                           # Machine-readable output
php artisan doctor:scan -v                               # Show all issues (not just first 5 per check)
```

Scan runs with no DB and no HTTP — safe for CI/CD.

## Understanding scan output

Each line shows:
- `✓` green — check passed
- `✗` yellow — warning (potential issue)
- `✗` red — error (definitive problem)

Each failed check lists affected files with line numbers, the specific issue, and a suggested fix. The bottom shows total passed/failed with scan duration.

## What each check validates

### Routes
- **Valid Route Names** — named routes don't collide and use dot-separated convention
- **Controller Method Exists** — route actions point to real controller methods
- **Missing Controller** — route actions reference existing controller classes
- **Invalid Middleware** — route middleware aliases are registered in the Kernel
- **Duplicate URIs** — no two GET routes point to the same URI

### Views
- **Missing @extends** — `@extends('layout')` references exist in the view finder
- **Missing @include** — `@include('partial')` references resolve to real views
- **Missing @component** — `@component('name')` references exist
- **Stack/Push Mismatch** — every `@push('stack')` has a matching `@stack('stack')`

### Middleware
- **Middleware Not Registered** — middleware(): aliases in code are registered in the Kernel
- **Terminate Method Throws** — `terminate()` method doesn't re-throw exceptions

### Eloquent
- **Missing fillable/guarded** — models declare mass-assignment protection
- **Accessor/Mutator Style** — uses modern `Illuminate\Database\Eloquent\Casts\Attribute` style
- **Value vs first on null** — doesn't call `->value` or `->first` on a nullable variable
- **WithCount on undefined relation** — `withCount()` references real relationships

### Jobs
- **Job Has Handle Method** — job classes define `handle()`
- **Missing Job Class** — dispatched job classes exist
- **Job Tries Zero** — explicitly retryable jobs have `$tries > 0` or `retryUntil()`
- **Bus Chain** — chained jobs reference existing classes
- **Failed Job Table** — `failed_jobs` table exists when using database queue
- **Job Dependency Resolution** — job constructor dependencies are resolvable

### Events
- **Missing Listener** — event listener classes exist
- **Listener Handle Method** — listeners define `handle($event)`

### Mail
- **Mailable Missing View** — mailables reference existing view files
- **Mailable Variable Mismatch** — variables passed to `view()` match `{{ $var }}` references

### Database / Schema
- **Column Mismatch** — model `$fillable`/`$casts` columns exist in the database table
- **Invalid Casts** — cast types are valid Eloquent cast types

### Storage
- **Undefined Disk** — `Storage::disk('name')` references a configured disk
- **S3 URL Without Config** — S3 URL generation has proper config values set
- **StoreAs Path Traversal** — uploaded file paths don't allow traversal attacks
- **Missing Storage Symlink** — `public/storage` symlink exists (when configured)

### Config
- **Abort If Wrong HTTP Code** — `abort_if`/`abort_unless` use valid HTTP status codes
- **Early Config Access** — no config accessed before bootstrap completes

### Env
- **App Key** — `APP_KEY` is set and not the default
- **Env Example Mismatch** — `.env.example` and `.env` have matching keys
- **Missing Env Keys** — `env('KEY')` calls reference existing env vars

### Cache
- **Session Driver Mismatch** — session driver matches cache config expectations

### Gates / Policies
- **Missing Policy** — gate policy classes exist

### Validation
- **Authorize Always False** — form request `authorize()` doesn't hard-code `false`
- **Non-Existent Rule Class** — custom validation rule classes exist

### Container
- **Singleton After First Resolve** — singletons aren't re-registered after first resolution
- **Interface Bound to Deleted Concrete** — bound implementations exist

### Schedule
- **Scheduled Command Exists** — `$schedule->command('name')` references a real command
- **Deleted Command** — scheduled commands aren't pointing to removed Artisan commands
- **Overlapping Without Lock** — long-running scheduled commands use `withoutOverlapping()`

### Livewire / Blade / Components
- **Missing Livewire Component** — Livewire component classes exist
- **Missing Named Routes** — `route('name')` in Blade references real named routes
- **Anonymous Component** — anonymous Blade component templates exist
- **Component Class** — class-based Blade components exist
- **Component Namespace** — component namespace paths resolve

## After generating new code, always run

```php
php artisan doctor:scan
```

## Before fixing any reported issue

**Verify the problem is real before making changes.** Doctor does static analysis — it can produce false positives. For every failure:

1. Read the reported file and confirm the issue actually exists
2. Check whether the referenced class, view, route, middleware alias, disk, env key, or relationship genuinely resolves at runtime
3. If it is a false positive (e.g. a middleware alias registered dynamically, a view loaded by a package, a model column added by a migration you haven't run), **do not change the code** — the check can be narrowed with `--only` to skip that category
4. Only fix issues you've confirmed are real

Fix any **confirmed** failures before committing. Common fixes:

- **Invalid middleware** → register the alias in `bootstrap/app.php` or `app/Http/Kernel.php`
- **Missing @extends/@include** → create the view file or fix the path
- **Column Mismatch** → run `php artisan migrate` or update the model's `$fillable`
- **Missing Env Keys** → add the key to `.env.example`
- **Undefined Disk** → add the disk to `config/filesystems.php`
- **Job Tries Zero** → add `public $tries = 3;` to the job class

## Disabling a check

To temporarily skip a failing check, pass `--only` with the categories you want:

```
php artisan doctor:scan --only=routes,views,eloquent
```
