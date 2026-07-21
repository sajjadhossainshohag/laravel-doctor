<?php

namespace SajjadHossain\Doctor\Checks\Debug;

use PhpParser\Node;
use PhpParser\Node\Expr\Exit_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\BladeAstCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class DebugStatementLeftInCheck extends BladeAstCheck
{
    private array $scanPaths = [];

    private const DEBUG_FUNCTIONS = ['dd', 'ddd', 'dump', 'var_dump', 'ray', 'print_r', 'phpinfo'];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Debug Statement Left In Code';
    }

    public function category(): string
    {
        return 'debug';
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: config('doctor.scan_paths', [app_path(), resource_path('views')]);
        $targetFns = self::DEBUG_FUNCTIONS;

        foreach ($this->scanPhpFiles($paths) as $file) {
            $raw = $this->stripComments($file['content']);
            $stmts = $this->parseBlade($raw);
            if ($stmts === null) {
                continue;
            }

            $visitor = new class($targetFns) extends NodeVisitorAbstract {
                private array $fns;

                public array $issues = [];

                public function __construct(array $fns)
                {
                    $this->fns = $fns;
                }

                public function enterNode(Node $node): void
                {
                    if ($node instanceof Exit_) {
                        $this->issues[] = [
                            'line' => $node->getLine(),
                            'function' => 'die/exit',
                        ];
                        return;
                    }

                    if (!$node instanceof FuncCall || !$node->name instanceof Node\Name) {
                        return;
                    }

                    $parts = explode('\\', $node->name->toString());
                    $name = end($parts);

                    if (in_array($name, $this->fns, true)) {
                        $this->issues[] = [
                            'line' => $node->getLine(),
                            'function' => $name,
                        ];
                    }
                }
            };

            $this->traverse($stmts, $visitor);

            foreach ($visitor->issues as $issue) {
                $locations[] = [
                    'file' => $file['path'],
                    'line' => $issue['line'],
                    'function' => $issue['function'],
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No debug statements found.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' debug statement(s) found.',
            locations: $locations,
            suggestion: 'Remove debug calls (dd, dump, var_dump, ray, print_r, phpinfo, die, exit) before committing.',
        );
    }
}
