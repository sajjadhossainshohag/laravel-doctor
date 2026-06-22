<?php

namespace SajjadHossain\Doctor\Checks\Env;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class EnvExampleMismatchCheck implements HealthCheck
{
    public function name(): string
    {
        return '.env vs .env.example Mismatch';
    }

    public function category(): string
    {
        return 'env';
    }

    public function severity(): Severity
    {
        return Severity::Info;
    }

    public function run(): CheckResult
    {
        $envKeys = array_keys($this->parseEnvFile(base_path('.env')));
        $envExampleKeys = array_keys($this->parseEnvFile(base_path('.env.example')));
        $locations = [];

        // The direction that actually indicates a configuration problem is
        // the reverse of what the old check did: a key listed in
        // .env.example but missing from .env means the developer's
        // .env has not been updated to declare a value for a key the
        // application expects. Keys present in .env but absent from
        // .env.example are often intentional (local-only).
        $missingFromEnv = array_diff($envExampleKeys, $envKeys);
        foreach ($missingFromEnv as $key) {
            $locations[] = [
                'key' => $key,
                'issue' => 'Present in .env.example but missing from .env',
            ];
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: '.env declares every key that .env.example documents.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' key(s) in .env.example missing from .env.',
            locations: $locations,
            suggestion: 'Add the missing key(s) to .env (with a local value) so the application can read them.',
        );
    }

    private function parseEnvFile(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $vars = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                $parts = explode('=', $line, 2);
                $vars[trim($parts[0])] = trim($parts[1]);
            }
        }

        return $vars;
    }
}
