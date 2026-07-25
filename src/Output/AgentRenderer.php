<?php

namespace SajjadHossain\Doctor\Output;

use SajjadHossain\Doctor\DTOs\CheckResult;

class AgentRenderer
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
                $issues[] = [
                    'check' => $result->check,
                    'category' => $result->category,
                    'severity' => $result->severity->value,
                    'message' => $result->message,
                    'locations' => $result->locations,
                    'suggestion' => $result->suggestion,
                ];

                match ($result->severity) {
                    \SajjadHossain\Doctor\Enums\Severity::Error => $errors++,
                    \SajjadHossain\Doctor\Enums\Severity::Warning => $warnings++,
                    default => $info++,
                };
            }
        }

        $payload = [
            'status' => empty($issues) ? 'pass' : 'fail',
            'summary' => [
                'total_checks' => count($results),
                'passed' => $passed,
                'errors' => $errors,
                'warnings' => $warnings,
                'info' => $info,
            ],
        ];

        if (!empty($issues)) {
            $payload['issues'] = count($issues);
            $payload['results'] = $issues;
        }

        return json_encode($payload, JSON_UNESCAPED_SLASHES);
    }
}
