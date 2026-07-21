<?php

namespace SajjadHossain\Doctor\Checks\Eloquent;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class GetThenCountCheck extends PhpAstCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return '->get()/->all() followed by ->count()';
    }

    public function category(): string
    {
        return 'eloquent';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: config('doctor.scan_paths', [app_path()]);

        foreach ($this->scanPhpFiles($paths) as $file) {
            $stmts = $this->parse($file['content']);
            if ($stmts === null) {
                continue;
            }

            $visitor = new class extends NodeVisitorAbstract {
                public array $issues = [];

                public function enterNode(Node $node): void
                {
                    if (!$node instanceof MethodCall) {
                        return;
                    }

                    if (
                        $node->name instanceof Node\Identifier
                        && $node->name->toString() === 'count'
                        && count($node->args) === 0
                    ) {
                        $var = $node->var;
                        if (
                            ($var instanceof MethodCall || $var instanceof StaticCall)
                            && $var->name instanceof Node\Identifier
                            && in_array($var->name->toString(), ['get', 'all'], true)
                            && count($var->args) === 0
                        ) {
                            $this->issues[] = ['line' => $node->getLine()];
                        }
                    }
                }
            };

            $this->traverse($stmts, $visitor);

            foreach ($visitor->issues as $issue) {
                $locations[] = [
                    'file' => $file['path'],
                    'line' => $issue['line'],
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No ->get()/->all() followed by ->count() chains detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' ->get()/->all() followed by ->count() chain(s) detected.',
            locations: $locations,
            suggestion: 'Replace ->get()/->all() with ->count() directly to avoid hydrating all models.',
        );
    }
}
