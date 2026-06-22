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
        $cipher = config('app.cipher', 'AES-256-CBC');

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

        // Modern Laravel APP_KEY is `base64:<random>`.
        if (! str_starts_with($appKey, 'base64:')) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: false,
                message: 'APP_KEY is not in the expected `base64:...` format.',
                suggestion: 'Run "php artisan key:generate" to generate a properly-formatted APP_KEY.',
            );
        }

        $decoded = base64_decode(substr($appKey, 7), true);
        if ($decoded === false) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: false,
                message: 'APP_KEY payload is not valid base64.',
                suggestion: 'Run "php artisan key:generate" to generate a valid APP_KEY.',
            );
        }

        $expectedLength = $this->expectedKeyLengthBytes($cipher);
        if ($expectedLength !== null && strlen($decoded) !== $expectedLength) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: false,
                message: "APP_KEY decoded length is ".strlen($decoded)." bytes but cipher '{$cipher}' requires {$expectedLength} bytes.",
                suggestion: 'Run "php artisan key:generate" to generate a key of the correct length for the configured cipher.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: true,
            message: 'APP_KEY is configured and valid for the active cipher.',
        );
    }

    private function expectedKeyLengthBytes(string $cipher): ?int
    {
        // Map common ciphers to the byte length of the key the encrypter
        // expects. For unknown ciphers we return null to avoid false
        // positives.
        $map = [
            'AES-128-CBC' => 16,
            'AES-128-CBC-HMAC-SHA1' => 16,
            'AES-128-CFB' => 16,
            'AES-128-CFB1' => 16,
            'AES-128-CFB8' => 16,
            'AES-128-CTR' => 16,
            'AES-128-ECB' => 16,
            'AES-128-OFB' => 16,
            'AES-192-CBC' => 24,
            'AES-192-CFB' => 24,
            'AES-192-CFB1' => 24,
            'AES-192-CFB8' => 24,
            'AES-192-CTR' => 24,
            'AES-192-ECB' => 24,
            'AES-192-OFB' => 24,
            'AES-256-CBC' => 32,
            'AES-256-CBC-HMAC-SHA1' => 32,
            'AES-256-CFB' => 32,
            'AES-256-CFB1' => 32,
            'AES-256-CFB8' => 32,
            'AES-256-CTR' => 32,
            'AES-256-ECB' => 32,
            'AES-256-OFB' => 32,
        ];

        return $map[$cipher] ?? null;
    }
}
