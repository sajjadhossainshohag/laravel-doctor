<?php

namespace SajjadHossain\Doctor\Checks\Views;

use SajjadHossain\Doctor\BladeAstCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

/**
 * Validates @extends('layout.name') references using Laravel's
 * booted view finder (view()->exists()), which already incorporates
 * every loadViewsFrom() / View::addNamespace() mapping registered by
 * service providers.
 *
 * Uses a simple regex on the raw Blade source instead of the
 * compiled-PHP AST because Blade compiles both @extends and @include
 * to the identical $__env->make(...) call shape — only @extends
 * emits the call in a footer appended after every other compiled
 * statement, making AST‑based disambiguation fragile.
 *
 * CLI / multi‑theme limitation:
 * If a provider registers a namespace hint whose directory depends on
 * run‑time request context (e.g. site_theme()), `php artisan doctor:scan`
 * has no HTTP request, so the namespace resolves to whatever the
 * *default* context produces. Validate additional themes by re‑running
 * the scan after swapping the theme context.
 */
class MissingExtendsCheck extends BladeAstCheck
{
    public function name(): string
    {
        return 'Missing @extends Layouts';
    }

    public function category(): string
    {
        return 'views';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];

        foreach ($this->scanViewFiles() as $file) {
            $raw = $this->stripComments($file['content']);

            if (preg_match('/@extends\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $raw, $m, PREG_OFFSET_CAPTURE)) {
                $layoutName = $m[1][0];
                $offset = $m[0][1];
                $line = substr_count(substr($raw, 0, $offset), "\n") + 1;

                if (! view()->exists($layoutName)) {
                    $locations[] = [
                        'file' => $file['path'],
                        'line' => $line,
                        'layout' => $layoutName,
                    ];
                }
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All @extends layouts resolve to existing views.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' @extends layout(s) not found.',
            locations: $locations,
            suggestion: 'Create the missing layout or correct the @extends path.',
        );
    }
}
