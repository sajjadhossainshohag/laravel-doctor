<?php

namespace SajjadHossain\Doctor\Checks\Eloquent;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Stmt\If_;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class ValueVsFirstOnNullCheck extends PhpAstCheck
{
    public function name(): string
    {
        return '->first()->column on Null Result';
    }

    public function category(): string
    {
        return 'eloquent';
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = config('doctor.scan_paths', [app_path(), resource_path('views')]);

        foreach ($this->scanPhpFiles($paths) as $file) {
            $stmts = $this->parse($file['content']);
            if ($stmts === null) {
                continue;
            }

            $visitor = new class extends NodeVisitorAbstract {
                /** @var array<int, array{line: int, guarded: bool}> */
                public array $firstCalls = [];
                private int $ifDepth = 0;

                public function enterNode(Node $node): void
                {
                    if ($node instanceof If_) {
                        $this->ifDepth++;
                        return;
                    }

                    // ->first()->someProperty (PropertyFetch)
                    if ($node instanceof PropertyFetch
                        && $node->var instanceof MethodCall
                        && $node->var->name instanceof Node\Identifier
                        && $node->var->name->toString() === 'first'
                        && count($node->var->args) === 0
                    ) {
                        $this->firstCalls[] = [
                            'line' => $node->getLine(),
                            'guarded' => $this->ifDepth > 0,
                        ];
                        return;
                    }

                    // ->first()->someMethod() (MethodCall)
                    if ($node instanceof MethodCall
                        && $node->name instanceof Node\Identifier
                        && $node->name->toString() !== 'first'
                        && $node->var instanceof MethodCall
                        && $node->var->name instanceof Node\Identifier
                        && $node->var->name->toString() === 'first'
                        && count($node->var->args) === 0
                    ) {
                        $this->firstCalls[] = [
                            'line' => $node->getLine(),
                            'guarded' => $this->ifDepth > 0,
                        ];
                    }
                }

                public function leaveNode(Node $node): void
                {
                    if ($node instanceof If_) {
                        $this->ifDepth--;
                    }
                }
            };

            $this->traverse($stmts, $visitor);

            foreach ($visitor->firstCalls as $call) {
                if ($call['guarded']) {
                    continue;
                }

                $content = $file['content'];
                $lines = explode("\n", $content);
                $lineIdx = $call['line'] - 1;
                $contextStart = max(0, $lineIdx - 5);
                $contextLines = array_slice($lines, $contextStart, $lineIdx - $contextStart + 1);
                $context = implode("\n", $contextLines);

                if (preg_match('/\?\s*->\s*first/', $context)) {
                    continue;
                }
                if (preg_match('/\b(groupBy|partition)\s*\(/', $context)
                    && preg_match('/\b(map|each|filter|transform)\s*\(\s*function\s*\(/', $context)) {
                    continue;
                }
                if (preg_match('/if\s*\([^)]*(?:->count\s*\(\s*\)\s*[!><]|->isNotEmpty\s*\(\s*\))/', $context)) {
                    continue;
                }

                $locations[] = [
                    'file' => $file['path'],
                    'line' => $call['line'],
                    'issue' => '->first()->property called without null check',
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No unsafe ->first()->property chains detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' unsafe ->first()->property chain(s) detected.',
            locations: $locations,
            suggestion: 'Use ->value(\'column\') or optional($result) to avoid null dereference.',
        );
    }
}
