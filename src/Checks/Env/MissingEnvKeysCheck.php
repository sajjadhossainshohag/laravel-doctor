<?php

namespace SajjadHossain\Doctor\Checks\Env;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class MissingEnvKeysCheck implements HealthCheck
{
    private ?string $envFilePath = null;
    private array $configPaths = [];

    public function withEnvFile(string $path): static
    {
        $this->envFilePath = $path;
        return $this;
    }

    public function withConfigPaths(array $paths): static
    {
        $this->configPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Missing .env Keys';
    }

    public function category(): string
    {
        return 'env';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $configKeys = $this->findEnvCallsInConfig();
        $envKeys = $this->parseEnvFile($this->envFilePath ?? base_path('.env'));
        $locations = [];

        foreach ($configKeys as $key) {
            if (!isset($envKeys[$key])) {
                $locations[] = [
                    'key' => $key,
                    'issue' => 'Referenced in config but missing from .env',
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All config env() keys are present in .env.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' env key(s) referenced in config but missing from .env.',
            locations: $locations,
            suggestion: 'Add the missing keys to your .env file.',
        );
    }

    private function findEnvCallsInConfig(): array
    {
        $keys = [];
        $configPath = $this->configPaths[0] ?? config_path();

        if (!is_dir($configPath)) {
            return [];
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($configPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getRealPath());
            preg_match_all('/env\([\'"]([^\'"]+)[\'"]/', $content, $matches);
            $keys = array_merge($keys, $matches[1]);
        }

        return array_unique($keys);
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
