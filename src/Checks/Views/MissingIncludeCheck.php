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
 * Validates @include('view.name') references using Laravel's
 * booted view finder (view()->exists()), which already incorporates
 * every loadViewsFrom() / View::addNamespace() mapping registered by
 * service providers.
 *
 * Blade compiles both @extends and @include to the identical
 * $__env->make(...) call. However, @extends emits its call in a
 * footer *after* every inline statement, so the extends node is
 * always the last matching node in traversal order. We detect and
 * exclude that trailing node when an @extends exists in the source.
 *
 * CLI / multi‑theme limitation:
 * If a provider registers a namespace hint whose directory depends on
 * run‑time request context (e.g. site_theme()), `php artisan doctor:scan`
 * has no HTTP request, so the namespace resolves to whatever the
 * *default* context produces. Validate additional themes by re‑running
 * the scan after swapping the theme context.
 */
class MissingIncludeCheck extends BladeAstCheck
{
    public function name(): string
    {
        return 'Missing @include Views';
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
        $scanned = 0;
        $locations = [];

        foreach ($this->scanViewFiles() as $file) {
            $raw = $this->stripComments($file['content']);
            $stmts = $this->parseBlade($raw);
            if ($stmts === null) {
                continue;
            }

            $hasExtends = (bool) preg_match('/@extends\s*\(/', $raw);

            $visitor = new class extends NodeVisitorAbstract {
                public array $views = [];

                public function enterNode(Node $node): void
                {
                    if ($node instanceof MethodCall
                        && $node->name instanceof Node\Identifier
                        && $node->name->toString() === 'make'
                        && $node->var instanceof Variable
                        && $node->var->name === '__env'
                        && count($node->args) > 0
                        && $node->args[0]->value instanceof String_
                    ) {
                        $this->views[] = $node->args[0]->value->value;
                    }
                }
            };

            $this->traverse($stmts, $visitor);

            // The @extends footer call is always the last matching
            // $__env->make(...) node in traversal order — remove it
            // so it is never misattributed as an @include.
            if ($hasExtends && count($visitor->views) > 0) {
                array_pop($visitor->views);
            }

            $lines = $this->mapDirectiveLines($raw, 'include');

            foreach ($visitor->views as $viewName) {
                $scanned++;
                $line = count($lines) > 0 ? array_shift($lines) : null;
                if (! view()->exists($viewName)) {
                    $locations[] = [
                        'file' => $file['path'],
                        'line' => $line,
                        'view' => $viewName,
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
                message: "All {$scanned} @include references resolve to existing views.",
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . " @include reference(s) point to missing views.",
            locations: $locations,
            suggestion: 'Create the missing view file or correct the @include path.',
        );
    }
}
