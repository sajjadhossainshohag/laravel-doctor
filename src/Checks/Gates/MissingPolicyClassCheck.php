<?php

namespace SajjadHossain\Doctor\Checks\Gates;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class MissingPolicyClassCheck extends PhpAstCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Policy Registered But Class Missing';
    }

    public function category(): string
    {
        return 'gates';
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
                public array $policies = [];

                public function enterNode(Node $node): void
                {
                    if (!$node instanceof StaticCall) {
                        return;
                    }
                    if (!$node->class instanceof Node\Name || $node->class->toString() !== 'Gate') {
                        return;
                    }
                    if (!$node->name instanceof Node\Identifier || $node->name->toString() !== 'policy') {
                        return;
                    }
                    if (count($node->args) < 2) {
                        return;
                    }

                    $arg0 = $node->args[0]->value;
                    $arg1 = $node->args[1]->value;

                    if ($arg0 instanceof ClassConstFetch && $arg1 instanceof ClassConstFetch) {
                        $this->policies[] = [
                            'line' => $node->getLine(),
                            'policy' => $arg1->class->toString(),
                        ];
                    }
                }
            };

            $this->traverse($stmts, $visitor);

            foreach ($visitor->policies as $policyInfo) {
                $policyClass = $policyInfo['policy'];
                $resolved = $this->resolveFqcn($file['content'], $policyClass, $stmts);

                if ($resolved !== null && class_exists($resolved)) {
                    continue;
                }

                if (!str_contains($policyClass, '\\')) {
                    $prefixed = 'App\\Policies\\' . $policyClass;
                    if (class_exists($prefixed)) {
                        continue;
                    }
                }

                $locations[] = [
                    'file' => $file['path'],
                    'line' => $policyInfo['line'],
                    'issue' => "Policy class '{$policyClass}' does not exist",
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All registered policy classes exist.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' registered policy class(es) not found.',
            locations: $locations,
            suggestion: 'Create the missing policy class or remove the Gate::policy() registration.',
        );
    }
}
