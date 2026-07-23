<?php

namespace SajjadHossain\Doctor\Output;

use SajjadHossain\Doctor\DTOs\CheckResult;

class AgentRenderer
{
    public function render(array $results): string
    {
        $issues = [];

        foreach ($results as $result) {
            if (!$result->passed) {
                $issues[] = [
                    'check' => $result->check,
                    'category' => $result->category,
                    'severity' => $result->severity->value,
                    'message' => $result->message,
                    'locations' => $result->locations,
                    'suggestion' => $result->suggestion,
                ];
            }
        }

        $payload = [
            'status' => empty($issues) ? 'pass' : 'fail',
            'issues' => count($issues),
        ];

        if (!empty($issues)) {
            $payload['results'] = $issues;
        }

        return json_encode($payload, JSON_UNESCAPED_SLASHES);
    }
}
