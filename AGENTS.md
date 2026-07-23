# Laravel Doctor — AGENTS.md

## Identity

- **Composer library** (`sajjadhossainshohag/laravel-doctor`), not a standalone app.
- Static analysis tool for Laravel codebases. Scans PHP/Blade source for 50+ issues across 18 categories.
- PHP ^8.1, supports Laravel ^10.0|^11.0|^12.0|^13.0.

## Commands

| Command | Purpose |
|---|---|
| `vendor/bin/phpunit` | Run all tests (no composer script defined) |
| `vendor/bin/phpunit --testsuite=Unit` | Unit tests only |
| `vendor/bin/phpunit --testsuite=Feature` | Feature tests only |
| `vendor/bin/phpunit --filter="test_method_name"` | Single test |
| `php artisan doctor:scan` | Run health scan in a Laravel project |
| `php artisan doctor:scan --only=routes,views` | Filter by category |
| `php artisan doctor:scan --json` | JSON output (full metadata) |
| `php artisan doctor:scan --format=agent` | Minimal JSON for AI agents (auto-detected) |
| `php artisan doctor:scan --fail-on=error,warning` | Exit 1 if issues found |
| `php artisan doctor:scan --no-cache` | Skip cached results |
| `php artisan doctor:scan --parallel` | Run checks in parallel subprocesses |
| `php artisan doctor:scan --parallel --workers=8` | Parallel with custom worker count |
| `php artisan doctor:cache:clear` | Clear cached scan results |
| `php artisan vendor:publish --tag=doctor-config` | Publish config to Laravel project |
| `php artisan doctor:worker --only=... --output=...` | (Internal) Run a subset of checks for parallel workers |

No linter, formatter, or static analysis tooling is configured.

## Architecture

- **Contract:** `SajjadHossain\Doctor\Contracts\HealthCheck` — `name()`, `category()`, `severity()`, `run(): CheckResult`.
- **CheckResult DTO** (`src/DTOs/CheckResult.php`): readonly properties — `check`, `category`, `severity`, `passed`, `message`, `locations`, `suggestion`.
- **Severity enum** (`src/Enums/Severity.php`): `Error`, `Warning`, `Info` (backed: `'error'`, `'warning'`, `'info'`).
- **Registry pattern:** `CheckRegistry` holds class strings, registered in `DoctorServiceProvider::boot()`. All 50+ checks registered there.
- **AST analysis:** `PhpAstCheck` wraps `nikic/php-parser ^5.0` — provides `parse()`, `traverse()`, `scanPhpFiles()`, `resolveFqcn()`. `BladeAstCheck` extends it — compiles Blade → PHP, then parses.
- **In-memory caches** (static, per-process): `PhpAstCheck` shares a single `Parser` instance and caches file contents (bounded to 1000 entries). `BladeAstCheck` caches compiled Blade output.
- **Ignore patterns:** `scanPhpFiles()` applies `config('doctor.ignore.{category}')` centrally — all checks skip vendor/noisy files automatically.
- **Persistent result cache:** `ScanResultCache` backed by Laravel Cache. Categories cached independently, invalidation via `--no-cache` or `doctor:cache:clear`.
- **Parallel execution:** `ParallelRunner` spawns subprocesses via `proc_open` — each runs `doctor:worker --only=... --output=...` with category groups. Results serialized to temp files, merged in the parent process. Fallback to sequential on worker failure.
- **Agent detection:** `laravel/agent-detector` (optional — `suggest` in composer.json) detects OpenCode, Claude Code, Cursor, Copilot, etc. When detected (or `--format=agent` is passed), `ScanCommand` suppresses all ANSI/progress output and uses `AgentRenderer` for minimal JSON: `{"status":"pass","issues":0}`. If `laravel/agent-detector` is not installed (requires PHP 8.2+), only the explicit `--format=agent` flag triggers agent output.
- **Allowlisted env keys:** `MissingEnvKeysCheck` reads `config('doctor.allowlisted_env_keys')` — a default list of 48 known-optional Laravel stock env vars (session, cache, database, mail, queue, etc.). Users can override via published config. Test seam: `withAllowlistedKeys()`.
- **Entrypoints:** `DoctorServiceProvider`, `ScanCommand`, `CacheClearCommand`, `WorkerCommand` (internal). Facade alias: `Doctor`.

## Namespace map

| Namespace | Path |
|---|---|
| `SajjadHossain\Doctor\` | `src/` |
| `SajjadHossain\Doctor\Tests\` | `tests/` |

Check implementations live under `src/Checks/{Category}/`.

## Testing

- Uses `orchestra/testbench` (^8.0|^9.0|^10.0|^11.0). Base: `tests/TestCase.php` extends `Orchestra\Testbench\TestCase`.
- Test helpers: `assertCheckFailed(CheckResult $result, Severity $severity, ?string $messageContains = null)` and `assertCheckPassed(CheckResult $result)`.
- **31 Unit** tests (isolated check logic), **18 Feature** tests (full Laravel boot, command integration).
- Fixtures in `tests/Fixtures/` mirror a real Laravel app (Models, Jobs, Mail, routes, views, etc.).
- phpunit.xml sets `APP_ENV=testing`, `DB_CONNECTION=sqlite` (`:memory:`), `CACHE_DRIVER=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`.

## CI

`.github/workflows/ci.yml` — matrix: PHP 8.1/8.2/8.3/8.4 × Laravel 10/11/12/13 × prefer-lowest/prefer-stable. Excludes PHP 8.1 + Laravel 12/13, PHP 8.2 + Laravel 13. Coverage disabled.

## Conventions

- PHP 8.1+ features used throughout: readonly properties, enums, named arguments.
- Checks are stateless — instantiated fresh per scan run. No constructor injection; use fluent setters for test seams (e.g. `withPaths()`, `withEnvFile()`).
- `composer.lock` is gitignored. CI regenerates it each run.
