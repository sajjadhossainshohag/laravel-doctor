---
name: laravel-doctor
description: Use Laravel Doctor to scan a Laravel project for code health issues — broken routes, missing views, schema mismatches, invalid middleware, and other static-analysis problems. Use when running doctor:scan, interpreting scan output, fixing issues Doctor reports, or ensuring new code passes all checks.
---

# Laravel Doctor

## When to use this skill

Use this skill when running `php artisan doctor:scan`, debugging a failing check, or writing new code that should pass Laravel Doctor's static analysis. Always run the scan after generating routes, views, middleware, controllers, jobs, events, mailables, or Eloquent models.

## Running the scan

```
php artisan doctor:scan
```

Common options:

```
php artisan doctor:scan --only=routes,views              # Run specific categories only
php artisan doctor:scan --fail-on=error                  # Exit code 1 on errors
php artisan doctor:scan -v                               # Show all issues (not just first 5 per check)
```

### Parsing results programmatically

**Always use `--json` when consuming output programmatically.** The human-readable format uses ✓/✗ glyphs and ANSI color codes — parsing it is unreliable. Example:

```
php artisan doctor:scan --json
```

JSON output is a structured array of check results with `check`, `category`, `severity`, `passed`, `message`, `locations[]`, and `suggestion` fields. Reserve the human-readable output only for relaying results directly to the user.

### Requirements at scan time

- **Database**: Required. Several checks query the actual database schema (`Schema::getColumnListing()`, `Schema::hasTable()`). The scan must run in an environment where the database connection is reachable and the schema matches the codebase.
- **No HTTP requests**: The scan does not make HTTP calls — it runs entirely within the CLI process.

## Understanding scan output

The human-readable console output shows each check as it runs with pass/fail status and per-check timing:

```
  [ 1/50] Valid Route Names...              ✓ 42ms
  [ 2/50] Missing @extends Layouts...       ✗ 312ms
  ...

  ✓  Missing Controller Method Exists
     All controller methods resolve to real controller methods.
  ✗  Column Mismatch (Eloquent vs DB)
     3 column mismatch(es) detected.
       app/Models/User.php: — $fillable column not found in DB table: `nickname`
     → Add a migration for the missing column [...]

  Results: 48 passed, 2 failed (1.62s)
```

Each line shows:
- `✓` green — check passed
- `✗` yellow — warning (potential issue, verify before acting)
- `✗` red — error (definitive problem, near-certain)

Failed checks list affected files with the specific issue and a suggested fix. Always verify before fixing — see the verification section below.

## Check reliability tiers

Not all checks are equally trustworthy. Some operate on ground-truth data (class existence, config lookups, DB schema), while others use AST pattern-matching heuristics that can produce false positives. Spend your verification effort accordingly.

### High-reliability checks (near-zero false positives)

These checks query definitive sources of truth — the framework's class loader, config, database schema, kernel contracts, or discrete boolean conditions. **Treat failures as real until proven otherwise.**

| Check | Why reliable |
|---|---|
| Missing Controller | `class_exists()` — the class is either there or it isn't |
| Controller Method Exists | `method_exists()` on a known class |
| Duplicate Route Names | hash-set collision detection on the named route array |
| Duplicate URIs | hash-set collision on HTTP method + URI |
| Invalid Middleware | Queries the booted Kernel's `getRouteMiddleware()` + Router's `getMiddleware()` — framework source of truth |
| Undefined Disk | `config('filesystems.disks')` lookup |
| App Key | Format validation against known cipher key lengths |
| Missing Env Keys | Diff between `env()` calls and `.env` keys |
| Env Example Mismatch | Key diff between `.env` and `.env.example` |
| Missing Job Class | `class_exists()` |
| Job Has Handle Method | `method_exists()` |
| Missing Listener Class | `class_exists()` |
| Listener Has Handle Method | `method_exists()` |
| Failed Job Table | `Schema::hasTable()` |
| Column Mismatch | `Schema::getColumnListing()` against the real database |
| Invalid Casts | `class_exists()` on cast types |
| Session Driver Mismatch | `Schema::hasTable('sessions')` |
| Missing Storage Symlink | `file_exists(public_path('storage'))` |
| S3 URL Without Config | Config key existence checks |
| StoreAs Path Traversal | Static string analysis for `..` |
| Abort If Wrong HTTP Code | Integer range check (400-599) |
| Bus Chain | `class_exists()` on chained job references |
| Job Dependency Resolution | Reflection-based constructor analysis |
| Missing Named Routes | `Route::has()` lookup |
| Anonymous Component | `view()->exists()` |
| Component Class | `view()->exists()` |
| Component Namespace | Directory existence check |
| Scheduled Command Exists | Artisan command registry lookup |
| Deleted Scheduled Command | String-to-registry matching |
| Mailable Missing View | `view()->exists()` |
| Middleware Not Registered | Kernel `getRouteMiddleware()` + `getMiddlewareGroups()` + Router `getMiddleware()` |
| Terminate Method Throws | AST analysis of `terminate()` method structure |
| Missing @extends | `view()->exists()` on raw Blade regex (no ambiguity with @include — resolved) |
| Missing @include | `view()->exists()` on PHP AST, correctly excludes @extends footer calls |
| Missing @component | `view()->exists()` on distinct `startComponent`/`renderComponent` AST nodes |
| Stack/Push Mismatch | Pairs `@push` names against `@stack` names in same template hierarchy |
| Missing fillable/guarded | Property existence on model classes |
| WithCount on undefined relation | `method_exists()` on the model class |
| Authorize Always False | Static `return false` detection in FormRequest `authorize()` |

### Lower-reliability checks (require manual confirmation)

These checks use AST pattern-matching heuristics or behavior inference that can produce false positives depending on coding style, indirection, or dynamic registration. **Always read the reported code and confirm the issue is real before fixing.**

| Check | Why unreliable |
|---|---|
| Value vs first on null | Pattern-matches `->first()->property` without null guard; can't see null guards in parent scopes, ternary assignments, or `optional()` wrappers |
| Accessor/Mutator Style | Heuristic detection of mixed old/new accessor patterns; legitimate mixed styles exist (e.g. mutator-only old-style with new-style accessors) |
| Non-Existent Rule Class | `class_exists()` on rule class references; rules can be registered dynamically via service providers or use short names that resolve through Laravel's rule name mapping |
| Singleton After First Resolve | Pattern-matches `$this->app->singleton()` inside `boot()`; complex service providers with conditional registration, deferred bindings, or framework-level singletons can trigger false positives |
| Early Config Access | Finds `env()` calls in provider `register()` methods; legitimate early-bound configs that don't depend on other providers are intentional |
| Overlapping Without Lock | Detects `$schedule->command()` chains missing `->withoutOverlapping()`; short-lived or idempotent commands often don't need locks |
| Mailable Variable Mismatch | Compares `view()` arguments against `{{ $var }}` references in templates; dynamic variable names, default values, or `@include`-passed variables create mismatches |
| Job Tries Zero | Detects `$tries = 0` on jobs; some jobs are intentionally fire-and-forget with external retry mechanisms |
| Missing Policy Class | `class_exists()` on policy references; policies can be auto-discovered by Laravel without explicit class mapping |
| Missing Livewire Component | `class_exists()` on component names; Livewire component discovery is namespace-based and may not match exact FQCN |

## Before fixing any reported issue

**Verify the problem is real before making changes.** Doctor does static analysis — it can produce false positives. For every failure:

1. Read the reported file and confirm the issue actually exists
2. Check whether the referenced class, view, route, middleware alias, disk, env key, or relationship genuinely resolves at runtime
3. For low-reliability checks especially: look for null guards, dynamic registrations, conditional bindings, or framework auto-discovery that makes the flagged pattern legitimate
4. If it is a false positive (e.g. a middleware alias registered dynamically, a view loaded by a package, a model column added by a migration you haven't run, a null guard outside the check's scan window), **do not change the code** — use `--only` to skip that category or temporarily ignore the check
5. Only fix issues you've confirmed are real

## After generating new code, always run

```
php artisan doctor:scan
```

## Common fixes

Fix only after verifying the issue is real:

- **Invalid middleware** → Register the alias in your middleware configuration. Check which structure your project uses: Laravel 11+ typically uses `bootstrap/app.php` `->withMiddleware()`, Laravel 10 and earlier use `app/Http/Kernel.php` `$routeMiddleware` / `$middlewareAliases`. Inspect your project rather than assuming.
- **Missing @extends/@include/@component** → Create the view file or fix the path
- **Column Mismatch** → Run `php artisan migrate` or update the model's `$fillable`
- **Missing Env Keys** → Add the key to `.env.example`
- **Undefined Disk** → Add the disk to `config/filesystems.php`
- **Job Tries Zero** → Add `public $tries = 3;` if the job should retry; if it's intentionally fire-and-forget, ignore
- **Middleware Not Registered** → Register in Kernel (same version check as Invalid Middleware above)

## Disabling a check

To temporarily skip a failing check, pass `--only` with the categories you want:

```
php artisan doctor:scan --only=routes,views,eloquent
```
