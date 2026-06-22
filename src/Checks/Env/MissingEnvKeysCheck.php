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
        // Collect config/env() calls as [key, hasDefault] pairs so we know
        // whether the call is missing-friendly.
        [$keysWithoutDefault, $keysWithDefault] = $this->findEnvCallsInConfig();
        $envKeys = $this->parseEnvFile($this->envFilePath ?? base_path('.env'));
        $locations = [];

        // Only flag env() calls that have NO default — those are the ones
        // that will silently produce null and break the application when
        // the key is missing from .env.
        foreach ($keysWithoutDefault as $key) {
            if (!isset($envKeys[$key])) {
                $locations[] = [
                    'key' => $key,
                    'issue' => 'Referenced in config (without a default) but missing from .env',
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All required config env() keys are present in .env.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' env key(s) referenced in config (without a default) but missing from .env.',
            locations: $locations,
            suggestion: 'Add the missing keys to your .env file, or add a default value in the config.',
        );
    }

    /**
     * Find env() calls in config files and split into two buckets:
     * those with NO default (env('FOO')) and those WITH a default
     * (env('FOO', 'bar')). Missing-key issues only matter for the
     * no-default bucket.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function findEnvCallsInConfig(): array
    {
        $noDefault = [];
        $withDefault = [];
        $configPath = $this->configPaths[0] ?? config_path();

        if (!is_dir($configPath)) {
            return [[], []];
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($configPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getRealPath());
            // Strip line/block comments.
            $stripped = preg_replace('#/\*.*?\*/#s', '', $content);
            $stripped = preg_replace('!//[^\n]*!', '', $stripped);

            // env('FOO')           — no default
            // env('FOO', $default) — has a default
            // We can't use a flat regex because the second arg may itself
            // contain commas in arrays — so we use a paren-balancing parse
            // for each env() call.
            if (! preg_match_all('/\benv\s*\(/', $stripped, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[0] as [$hit, $offset]) {
                $args = $this->readBalancedParens($stripped, $offset + strlen($hit) - 1);
                if ($args === null) {
                    continue;
                }

                // First arg must be the key as a string literal.
                if (! preg_match('/^\s*[\'"]([^\'"]+)[\'"]\s*(?:,|$)/', $args, $km)) {
                    continue;
                }
                $key = $km[1];

                // Determine whether a second argument is present.
                $rest = substr($args, strlen($km[0]));
                if (trim($rest) === '') {
                    $noDefault[] = $key;
                } else {
                    $withDefault[] = $key;
                }
            }
        }

        return [array_values(array_unique($noDefault)), array_values(array_unique($withDefault))];
    }

    private function readBalancedParens(string $haystack, int $open): ?string
    {
        if (! isset($haystack[$open]) || $haystack[$open] !== '(') {
            return null;
        }
        $depth = 0;
        $i = $open;
        $inString = false;
        $stringChar = '';
        $len = strlen($haystack);
        while ($i < $len) {
            $c = $haystack[$i];
            if ($inString) {
                if ($c === '\\') { $i += 2; continue; }
                if ($c === $stringChar) { $inString = false; }
            } else {
                if ($c === '\'' || $c === '"') { $inString = true; $stringChar = $c; }
                elseif ($c === '(') { $depth++; }
                elseif ($c === ')') {
                    $depth--;
                    if ($depth === 0) {
                        return substr($haystack, $open + 1, $i - $open - 1);
                    }
                }
            }
            $i++;
        }

        return null;
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