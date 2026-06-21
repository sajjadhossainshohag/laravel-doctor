<?php

namespace SajjadHossain\Doctor\Output;

use SajjadHossain\Doctor\DTOs\CheckResult;

class JsonRenderer
{
    public function render(array $results): string
    {
        $issues = [];
        $passed = 0;
        $errors = 0;
        $warnings = 0;
        $info = 0;

        foreach ($results as $result) {
            if ($result->passed) {
                $passed++;
            } else {
                $issues[] = $this->resultToArray($result);

                match ($result->severity->value) {
                    'error' => $errors++,
                    'warning' => $warnings++,
                    default => $info++,
                };
            }
        }

        return json_encode([
            'version' => '1.0',
            'scanned_at' => now()->toIso8601String(),
            'laravel_version' => app()->version(),
            'php_version' => phpversion(),
            'issues' => $issues,
            'summary' => [
                'total_checks' => count($results),
                'passed' => $passed,
                'errors' => $errors,
                'warnings' => $warnings,
                'info' => $info,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function resultToArray(CheckResult $r): array
    {
        return [
            'id' => str_replace(' ', '_', strtolower($r->check)),
            'category' => $r->category,
            'severity' => $r->severity->value,
            'message' => $r->message,
            'locations' => $r->locations,
            'suggestion' => $r->suggestion,
        ];
    }
}
