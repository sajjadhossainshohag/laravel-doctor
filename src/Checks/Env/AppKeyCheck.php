<?php

namespace SajjadHossain\Doctor\Checks\Env;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class AppKeyCheck implements HealthCheck
{
    public function name(): string
    {
        return 'APP_KEY Validation';
    }

    public function category(): string
    {
        return 'env';
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $appKey = config('app.key');

        if (empty($appKey)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: false,
                message: 'APP_KEY is empty.',
                suggestion: 'Run "php artisan key:generate" to set a valid APP_KEY.',
            );
        }

        if ($appKey === 'base64:' || str_starts_with($appKey, 'base64:') && strlen($appKey) < 20) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: false,
                message: 'APP_KEY appears to be a placeholder or incomplete.',
                suggestion: 'Run "php artisan key:generate" to generate a valid APP_KEY.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: true,
            message: 'APP_KEY is configured.',
        );
    }
}
