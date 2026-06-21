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

        $missingFromExample = array_diff($envKeys, $envExampleKeys);
        foreach ($missingFromExample as $key) {
            $locations[] = [
                'key' => $key,
                'issue' => 'Present in .env but missing from .env.example',
            ];
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: '.env and .env.example are in sync.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' key(s) in .env missing from .env.example.',
            locations: $locations,
            suggestion: 'Add the missing key(s) to .env.example with placeholder values.',
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
