<?php

namespace SajjadHossain\Doctor\Output;

use SajjadHossain\Doctor\DTOs\CheckResult;

class HtmlRenderer
{
    public function render(array $results): string
    {
        $rows = '';
        foreach ($results as $result) {
            $color = $result->passed ? '#16a34a' : ($result->severity->value === 'error' ? '#dc2626' : '#ca8a04');
            $status = $result->passed ? '✓' : '✗';
            $rows .= "<tr>
                <td style=\"color:{$color}\">{$status}</td>
                <td>{$result->check}</td>
                <td style=\"color:{$color}\">{$result->severity->value}</td>
                <td>{$result->message}</td>
            </tr>";
        }

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Laravel Doctor Report</title>
            <style>
                body { font-family: system-ui, sans-serif; max-width: 960px; margin: 2rem auto; padding: 0 1rem; }
                h1 { color: #1f2937; }
                table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
                th, td { padding: 0.5rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
                th { background: #f3f4f6; font-weight: 600; }
            </style>
        </head>
        <body>
            <h1>Laravel Doctor Report</h1>
            <p>Scanned at: {$this->now()}</p>
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Check</th>
                        <th>Severity</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>
        </body>
        </html>
        HTML;
    }

    private function now(): string
    {
        return now()->toIso8601String();
    }
}
