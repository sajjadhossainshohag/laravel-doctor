<?php

namespace SajjadHossain\Doctor\Checks\Config;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class AbortIfWrongHttpCodeCheck extends PhpAstCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'abort_if() / abort_unless() Wrong HTTP Code';
    }

    public function category(): string
    {
        return 'config';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: config('doctor.scan_paths', [app_path(), resource_path('views')]);

        $targetFns = ['abort_if', 'abort_unless'];

        foreach ($this->scanPhpFiles($paths) as $file) {
            $stmts = $this->parse($this->stripComments($file['content']));
            if ($stmts === null) {
                continue;
            }

            $visitor = new class($targetFns) extends NodeVisitorAbstract {
                private array $fns;
                public array $issues = [];

                public function __construct(array $fns) { $this->fns = $fns; }

                public function enterNode(Node $node): void
                {
                    if (!$node instanceof FuncCall || !$node->name instanceof Node\Name) {
                        return;
                    }
                    $parts = explode('\\', $node->name->toString());
                    $name = end($parts);

                    if (!in_array($name, $this->fns, true)) {
                        return;
                    }
                    if (count($node->args) < 2) {
                        return;
                    }

                    $codeArg = $node->args[1]->value;
                    $code = null;
                    if ($codeArg instanceof LNumber) {
                        $code = $codeArg->value;
                    } elseif ($codeArg instanceof Node\Scalar\String_) {
                        $code = (int) $codeArg->value;
                    }

                    if ($code !== null && $code >= 100 && $code <= 599 && $code < 400) {
                        $this->issues[] = [
                            'line' => $node->getLine(),
                            'fn' => $name,
                            'code' => $code,
                        ];
                    }
                }
            };

            $this->traverse($stmts, $visitor);

            foreach ($visitor->issues as $issue) {
                $locations[] = [
                    'file' => $file['path'],
                    'line' => $issue['line'],
                    'issue' => "{$issue['fn']}() with HTTP {$issue['code']} — expected 4xx/5xx error code",
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No suspicious HTTP codes in abort_if/abort_unless calls.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' abort_if/abort_unless call(s) with potentially wrong code.',
            locations: $locations,
            suggestion: 'Use appropriate 4xx (client error) or 5xx (server error) codes.',
        );
    }
}
