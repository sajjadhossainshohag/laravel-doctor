<?php

namespace SajjadHossain\Doctor\Checks\Middleware;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class TerminateMethodThrowsCheck extends PhpAstCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Middleware terminate() May Throw';
    }

    public function category(): string
    {
        return 'middleware';
    }

    public function severity(): Severity
    {
        return Severity::Info;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: [app_path('Http/Middleware')];

        $externalPrefixes = ['DB', 'Http', 'Log', 'Storage', 'Mail', 'Redis', 'Cache'];
        $riskyFuncs = ['event', 'dispatch'];

        foreach ($this->scanPhpFiles($paths) as $file) {
            $stmts = $this->parse($this->stripComments($file['content']));
            if ($stmts === null) {
                continue;
            }

            $visitor = new class($externalPrefixes, $riskyFuncs) extends NodeVisitorAbstract {
                private array $prefixes;
                private array $funcs;
                private bool $inTerminate = false;
                private bool $hasTryCatch = false;
                public array $issues = [];

                public function __construct(array $prefixes, array $funcs)
                {
                    $this->prefixes = $prefixes;
                    $this->funcs = $funcs;
                }

                public function enterNode(Node $node): void
                {
                    if ($node instanceof ClassMethod
                        && $node->name instanceof Node\Identifier
                        && $node->name->toString() === 'terminate'
                    ) {
                        $this->inTerminate = true;
                        $this->hasTryCatch = false;
                        return;
                    }

                    if (!$this->inTerminate) {
                        return;
                    }

                    if ($node instanceof TryCatch) {
                        $this->hasTryCatch = true;
                        return;
                    }

                    if ($this->hasTryCatch) {
                        return;
                    }

                    $isExternal = false;

                    if ($node instanceof StaticCall
                        && $node->class instanceof Node\Name
                    ) {
                        $parts = explode('\\', $node->class->toString());
                        $short = end($parts);
                        if (in_array($short, $this->prefixes, true)) {
                            $isExternal = true;
                        }
                    }

                    if ($node instanceof FuncCall
                        && $node->name instanceof Node\Name
                    ) {
                        $fn = $node->name->toString();
                        if (in_array($fn, $this->funcs, true)) {
                            $isExternal = true;
                        }
                    }

                    if ($isExternal) {
                        $this->issues[] = [
                            'line' => $node->getLine(),
                            'reason' => 'terminate() makes external calls without try/catch',
                        ];
                    }
                }

                public function leaveNode(Node $node): void
                {
                    if ($node instanceof ClassMethod
                        && $node->name instanceof Node\Identifier
                        && $node->name->toString() === 'terminate'
                    ) {
                        $this->inTerminate = false;
                    }
                }
            };

            $this->traverse($stmts, $visitor);

            foreach ($visitor->issues as $issue) {
                $locations[] = [
                    'file' => $file['path'],
                    'line' => $issue['line'],
                    'issue' => $issue['reason'] . ' — wrap them to keep cleanup work resilient after the response is sent',
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No risky terminate() methods detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: true,
            message: count($locations) . ' terminate() method(s) make external calls without try/catch.',
            locations: $locations,
            suggestion: 'Wrap terminate() logic in try/catch to keep cleanup work resilient after the response is sent.',
        );
    }
}
