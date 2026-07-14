<?php

namespace SajjadHossain\Doctor\Checks\Views;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\BladeAstCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

/**
 * Validates @component('component.name') references using Laravel's
 * booted view finder (view()->exists()), which already incorporates
 * every loadViewsFrom() / View::addNamespace() mapping registered by
 * service providers.
 *
 * @component compiles to $__env->startComponent('name') — a
 * distinct method name that does NOT collide with @extends/@include.
 * We intentionally only match startComponent / renderComponent;
 * the generic $__env->make(...) catch-all was removed because it
 * would misattribute extends/include calls as components.
 *
 * CLI / multi‑theme limitation:
 * If a provider registers a namespace hint whose directory depends on
 * run‑time request context (e.g. site_theme()), `php artisan doctor:scan`
 * has no HTTP request, so the namespace resolves to whatever the
 * *default* context produces. Validate additional themes by re‑running
 * the scan after swapping the theme context.
 */
class MissingComponentCheck extends BladeAstCheck
{
    public function name(): string
    {
        return 'Missing @component References';
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
            $stmts = $this->parseBlade($raw);
            if ($stmts === null) {
                continue;
            }

            $visitor = new class extends NodeVisitorAbstract {
                public array $components = [];

                public function enterNode(Node $node): void
                {
                    if ($node instanceof MethodCall
                        && $node->name instanceof Node\Identifier
                        && in_array($node->name->toString(), ['startComponent', 'renderComponent'], true)
                        && $node->var instanceof Variable
                        && $node->var->name === '__env'
                        && count($node->args) > 0
                        && $node->args[0]->value instanceof String_
                    ) {
                        $this->components[] = $node->args[0]->value->value;
                    }
                }
            };

            $this->traverse($stmts, $visitor);

            $lines = $this->mapDirectiveLines($raw, 'component');

            foreach ($visitor->components as $componentName) {
                if (!view()->exists($componentName)) {
                    $line = count($lines) > 0 ? array_shift($lines) : null;
                    $locations[] = [
                        'file' => $file['path'],
                        'line' => $line,
                        'component' => $componentName,
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
                message: 'All @component references resolve to existing views.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' @component reference(s) point to missing views.',
            locations: $locations,
            suggestion: 'Create the missing view file or correct the @component path.',
        );
    }
}
