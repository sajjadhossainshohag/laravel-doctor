<?php

namespace SajjadHossain\Doctor\Checks\Container;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class InterfaceBoundToDeletedConcreteCheck extends PhpAstCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Interface Bound to Deleted/Renamed Concrete';
    }

    public function category(): string
    {
        return 'container';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: [app_path('Providers')];

        foreach ($this->scanPhpFiles($paths) as $file) {
            $stmts = $this->parse($this->stripComments($file['content']));
            if ($stmts === null) {
                continue;
            }

            $visitor = new class extends NodeVisitorAbstract {
                public array $bindings = [];

                public function enterNode(Node $node): void
                {
                    // $this->app->bind(Interface::class, Concrete::class)
                    if (!$node instanceof MethodCall || !$node->name instanceof Node\Identifier) {
                        return;
                    }
                    if ($node->name->toString() !== 'bind') {
                        return;
                    }
                    // Must be $this->app->bind(...) where $this->app is a PropertyFetch
                    if (!$node->var instanceof PropertyFetch) {
                        return;
                    }
                    $prop = $node->var;
                    if (!$prop->var instanceof Variable || $prop->var->name !== 'this') {
                        return;
                    }
                    if (!$prop->name instanceof Node\Identifier || $prop->name->toString() !== 'app') {
                        return;
                    }

                    if (count($node->args) < 2) {
                        return;
                    }

                    $arg0 = $node->args[0]->value;
                    $arg1 = $node->args[1]->value;

                    if ($arg0 instanceof ClassConstFetch && $arg1 instanceof ClassConstFetch) {
                        $abstract = $arg0->class->toString();
                        $concrete = $arg1->class->toString();
                        $this->bindings[] = [
                            'line' => $node->getLine(),
                            'concrete' => $concrete,
                            'abstract' => $abstract,
                        ];
                    }
                }
            };

            $this->traverse($stmts, $visitor);

            foreach ($visitor->bindings as $binding) {
                $resolved = $this->resolveFqcn($file['content'], $binding['concrete'], $stmts);
                if ($resolved !== null && ! class_exists($resolved)) {
                    $locations[] = [
                        'file' => $file['path'],
                        'line' => $binding['line'],
                        'issue' => "Binding references non-existent class '{$resolved}'",
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
                message: 'All container bindings reference existing classes.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' binding(s) reference non-existent class(es).',
            locations: $locations,
            suggestion: 'Fix the binding to reference an existing class, or remove the binding.',
        );
    }
}
