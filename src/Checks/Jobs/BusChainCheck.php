<?php

namespace SajjadHossain\Doctor\Checks\Jobs;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class BusChainCheck extends PhpAstCheck
{
    public function name(): string
    {
        return 'Bus Chain Jobs';
    }

    public function category(): string
    {
        return 'jobs';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = config('doctor.scan_paths', [app_path()]);

        foreach ($this->scanPhpFiles($paths) as $file) {
            $stmts = $this->parse($this->stripComments($file['content']));
            if ($stmts === null) {
                continue;
            }

            $visitor = new class extends NodeVisitorAbstract {
                public array $chains = [];

                public function enterNode(Node $node): void
                {
                    if (!$node instanceof StaticCall) {
                        return;
                    }
                    if (!$node->class instanceof Node\Name) {
                        return;
                    }

                    $className = $node->class->toString();
                    $shortName = substr($className, strrpos($className, '\\') + 1);
                    if ($shortName !== 'Bus') {
                        return;
                    }
                    if (!$node->name instanceof Node\Identifier || $node->name->toString() !== 'chain') {
                        return;
                    }
                    if (count($node->args) < 1) {
                        return;
                    }

                    $arg = $node->args[0]->value;
                    if (!$arg instanceof Array_) {
                        return;
                    }

                    foreach ($arg->items as $item) {
                        if ($item === null) {
                            continue;
                        }
                        $val = $item->value;
                        $className = null;

                        if ($val instanceof ClassConstFetch && $val->class instanceof Node\Name) {
                            $className = $val->class->toString();
                        } elseif ($val instanceof New_ && $val->class instanceof Node\Name) {
                            $className = $val->class->toString();
                        }

                        if ($className !== null) {
                            $this->chains[] = [
                                'line' => $node->getLine(),
                                'class' => $className,
                            ];
                        }
                    }
                }
            };

            $this->traverse($stmts, $visitor);

            foreach ($visitor->chains as $chainInfo) {
                $resolved = $this->resolveFqcn($file['content'], $chainInfo['class']);
                $checkClass = $resolved ?? $chainInfo['class'];

                if (!class_exists($checkClass)) {
                    $locations[] = [
                        'file' => $file['path'],
                        'line' => $chainInfo['line'],
                        'job' => $checkClass,
                        'issue' => "Chained job class '{$chainInfo['class']}' does not exist",
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
                message: 'All Bus::chain() job classes exist.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' chained job class(es) not found.',
            locations: $locations,
            suggestion: 'Create the missing job class or fix the chain reference.',
        );
    }
}
