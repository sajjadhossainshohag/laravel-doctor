<?php

namespace SajjadHossain\Doctor\Checks\Storage;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class StoreAsPathTraversalCheck extends PhpAstCheck
{
    public function name(): string
    {
        return '->storeAs() Path Traversal Risk';
    }

    public function category(): string
    {
        return 'storage';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = config('doctor.scan_paths', [app_path(), resource_path('views')]);

        foreach ($this->scanPhpFiles($paths) as $file) {
            $stmts = $this->parse($this->stripComments($file['content']));
            if ($stmts === null) {
                continue;
            }

            $visitor = new class extends NodeVisitorAbstract {
                public array $issues = [];

                public function enterNode(Node $node): void
                {
                    if (!$node instanceof MethodCall
                        || !$node->name instanceof Node\Identifier
                        || $node->name->toString() !== 'storeAs'
                    ) {
                        return;
                    }

                    $args = $node->args;
                    if (count($args) < 2) {
                        return;
                    }

                    $pathArg = $args[0]->value;
                    $nameArg = $args[1]->value;

                    foreach ([$pathArg, $nameArg] as $arg) {
                        if ($arg instanceof String_) {
                            $val = $arg->value;
                            if (str_starts_with($val, '/') || str_starts_with($val, '\\')) {
                                $this->issues[] = [
                                    'line' => $node->getLine(),
                                    'reason' => 'storeAs() path is absolute',
                                ];
                                return;
                            }
                            if (str_contains($val, '../') || str_contains($val, '..\\')) {
                                $this->issues[] = [
                                    'line' => $node->getLine(),
                                    'reason' => 'storeAs() path contains a literal ".." segment',
                                ];
                                return;
                            }
                        } elseif (!$arg instanceof String_) {
                            $this->issues[] = [
                                'line' => $node->getLine(),
                                'reason' => 'storeAs() argument is a variable — verify it is not user-controlled',
                            ];
                            return;
                        }
                    }
                }
            };

            $this->traverse($stmts, $visitor);

            foreach ($visitor->issues as $issue) {
                $locations[] = [
                    'file' => $file['path'],
                    'line' => $issue['line'],
                    'issue' => $issue['reason'].' — may store to an unexpected location',
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No path traversal risks in storeAs() calls.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' storeAs() call(s) with a path traversal risk.',
            locations: $locations,
            suggestion: 'Sanitize paths and use a dedicated disk. Avoid ".." segments, absolute paths, and unvalidated user input.',
        );
    }
}
