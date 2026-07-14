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

            $lines = $this->mapDirectiveLines($raw, 'component');

            $visitor = new class extends NodeVisitorAbstract {
                public array $components = [];

                public function enterNode(Node $node): void
                {
                    // $__env->startComponent('name')
                    if ($node instanceof MethodCall
                        && $node->name instanceof Node\Identifier
                        && ($node->name->toString() === 'startComponent' || $node->name->toString() === 'renderComponent')
                        && $node->var instanceof Variable
                        && $node->var->name === '__env'
                        && count($node->args) > 0
                        && $node->args[0]->value instanceof String_
                    ) {
                        $this->components[] = $node->args[0]->value->value;
                    }
                    // $__env->make('name', ...) with 'component' intent
                    if ($node instanceof MethodCall
                        && $node->name instanceof Node\Identifier
                        && $node->name->toString() === 'make'
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
