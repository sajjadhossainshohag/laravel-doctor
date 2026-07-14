<?php

namespace SajjadHossain\Doctor\Checks\Validation;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class AuthorizeAlwaysFalseCheck extends PhpAstCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'authorize() Always Returns False';
    }

    public function category(): string
    {
        return 'validation';
    }

    public function severity(): Severity
    {
        return Severity::Info;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: [app_path('Http/Requests')];

        foreach ($this->scanPhpFiles($paths) as $file) {
            $stmts = $this->parse($this->stripComments($file['content']));
            if ($stmts === null) {
                continue;
            }

            $visitor = new class extends NodeVisitorAbstract {
                public array $found = [];
                public function enterNode(Node $node): void {
                    if (!$node instanceof ClassMethod || $node->name->toString() !== 'authorize') {
                        return;
                    }
                    if ($node->stmts !== null && count($node->stmts) === 1 && $node->stmts[0] instanceof Return_) {
                        $retVal = $node->stmts[0]->expr;
                        if ($retVal instanceof Node\Expr\ConstFetch && $retVal->name->toString() === 'false') {
                            $this->found[] = $node->getLine();
                        }
                    }
                }
            };

            $this->traverse($stmts, $visitor);

            foreach ($visitor->found as $line) {
                $locations[] = [
                    'file' => $file['path'],
                    'line' => $line,
                    'issue' => 'authorize() returns false — every request gets 403. Verify this is intentional.',
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No FormRequests with authorize() hardcoded to false detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: true,
            message: count($locations).' FormRequest(s) have authorize() hardcoded to false. This is informational — confirm this is intentional.',
            locations: $locations,
            suggestion: 'If this is unintended, replace "return false" with real authorization logic or "return true".',
        );
    }
}
