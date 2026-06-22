# Repository Guidelines

## Project Structure & Module Organization

This is a Laravel package for code health checks. Package source lives in `src/` under the `SajjadHossain\Doctor\` PSR-4 namespace. Tests live in `tests/Unit` and `tests/Feature` under `SajjadHossain\Doctor\Tests\`. Configuration, route, and database integration files are organized in `config/`, `routes/`, and `database/`. CI configuration is in `.github/`. Vendor dependencies are installed in `vendor/` and should not be edited directly.

## Build, Test, and Development Commands

- `composer install`: install PHP dependencies.
- `composer dump-autoload`: refresh Composer autoload mappings after adding classes.
- `vendor/bin/phpunit`: run the full test suite using `phpunit.xml`.
- `vendor/bin/phpunit --testsuite Unit`: run unit tests only.
- `vendor/bin/phpunit --testsuite Feature`: run feature tests only.

There is no frontend build pipeline in this package. Keep development focused on PHP package code and Laravel integration behavior.

## Coding Style & Naming Conventions

Use PHP 8.2+ features only when compatible with the supported Laravel versions. Follow PSR-4 class placement: a class such as `SajjadHossain\Doctor\Services\RouteScanner` belongs in `src/Services/RouteScanner.php`. Use 4-space indentation for PHP and XML files. Prefer descriptive class and method names that match their Laravel role, such as `DoctorServiceProvider`, console command classes, scanners, analyzers, and result DTOs. Keep public APIs small and avoid framework side effects during package boot unless required.

## Testing Guidelines

PHPUnit is configured through `phpunit.xml` with in-memory SQLite, array cache, sync queue, and testing environment variables. Place isolated behavior tests in `tests/Unit` and Laravel integration tests in `tests/Feature`. Name tests after the behavior under test, for example `RouteScannerTest.php` or `DoctorCommandTest.php`. Add or update tests for route checks, view resolution, schema checks, and service provider behavior when those areas change.

## Commit & Pull Request Guidelines

Recent commits use short, imperative summaries such as `Refactor CI dependency installation for improved security and compatibility`. Keep commit subjects concise and focused on the change. Pull requests should include a clear description, affected package areas, test results, and linked issues when applicable. Include screenshots or console output only when command behavior or developer-facing output changes.

## Security & Configuration Tips

Do not commit local secrets, `.env` files, or generated caches. Keep package defaults safe for test and CI environments. When adding configuration, document expected keys and avoid requiring application-specific credentials for basic health checks.
