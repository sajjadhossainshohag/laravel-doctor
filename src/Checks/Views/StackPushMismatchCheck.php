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

class StackPushMismatchCheck extends BladeAstCheck
{
    public function name(): string
    {
        return '@stack / @push Mismatches';
    }

    public function category(): string
    {
        return 'views';
    }

    public function severity(): Severity
    {
        return Severity::Info;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $allStacks = [];
        $allPushes = [];

        foreach ($this->scanViewFiles() as $file) {
            $raw = $this->stripComments($file['content']);
            $stmts = $this->parseBlade($raw);
            if ($stmts === null) {
                continue;
            }

            $visitor = new class extends NodeVisitorAbstract {
                public array $stacks = [];
                public array $pushes = [];

                public function enterNode(Node $node): void
                {
                    if (!$node instanceof MethodCall
                        || !$node->name instanceof Node\Identifier
                        || !$node->var instanceof Variable
                        || $node->var->name !== '__env'
                    ) {
                        return;
                    }
                    if (count($node->args) < 1 || !$node->args[0]->value instanceof String_) {
                        return;
                    }

                    $name = $node->name->toString();
                    $value = $node->args[0]->value->value;

                    if ($name === 'yieldPushContent') {
                        $this->stacks[] = $value;
                    } elseif ($name === 'startPush') {
                        $this->pushes[] = $value;
                    }
                }
            };

            $this->traverse($stmts, $visitor);

            foreach ($visitor->stacks as $stack) {
                $allStacks[$stack] = true;
            }
            foreach ($visitor->pushes as $push) {
                $allPushes[$push] = ($allPushes[$push] ?? 0) + 1;
            }
        }

        foreach ($allPushes as $pushName => $count) {
            if (!isset($allStacks[$pushName])) {
                $locations[] = [
                    'stack' => $pushName,
                    'pushes' => $count,
                    'issue' => '@push targets a stack name with no matching @stack in any scanned view',
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All @push targets have matching @stack definitions.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: true,
            message: count($locations) . ' @push target(s) do not have a matching @stack in any scanned view. This is informational — the @stack may live in a layout rendered outside these directories.',
            locations: $locations,
        );
    }
}
