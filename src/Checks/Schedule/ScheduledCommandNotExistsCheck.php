<?php

namespace SajjadHossain\Doctor\Checks\Schedule;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class ScheduledCommandNotExistsCheck extends PhpAstCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Scheduled Command Class Does Not Exist';
    }

    public function category(): string
    {
        return 'schedule';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];

        $paths = $this->scanPaths ?: [app_path('Console'), app_path('Providers')];
        $consoleRoute = base_path('routes/console.php');

        foreach ($this->scanPhpFiles($paths) as $file) {
            $stmts = $this->parse($file['content']);
            if ($stmts === null) {
                continue;
            }

            foreach ($this->extractCommandClasses($stmts, $file['content']) as $cmdInfo) {
                $checkClass = $cmdInfo['class'];
                if (!class_exists($checkClass)) {
                    $locations[] = [
                        'file' => $file['path'],
                        'line' => $cmdInfo['line'],
                        'issue' => "Scheduled command class '{$checkClass}' does not exist",
                    ];
                }
            }
        }

        if (is_file($consoleRoute)) {
            $content = file_get_contents($consoleRoute);
            $stmts = $this->parse($content);
            if ($stmts !== null) {
                foreach ($this->extractCommandClasses($stmts, $content) as $cmdInfo) {
                    $checkClass = $cmdInfo['class'];
                    if (!class_exists($checkClass)) {
                        $locations[] = [
                            'file' => $consoleRoute,
                            'line' => $cmdInfo['line'],
                            'issue' => "Scheduled command class '{$checkClass}' does not exist",
                        ];
                    }
                }
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All scheduled command classes exist.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' scheduled command class(es) not found.',
            locations: $locations,
            suggestion: 'Create the command class or fix the reference in the schedule.',
        );
    }

    private function extractCommandClasses(array $stmts, string $fileContent): array
    {
        $visitor = new class extends NodeVisitorAbstract {
            public array $commands = [];

            public function enterNode(Node $node): void
            {
                $classArg = null;

                if ($node instanceof StaticCall
                    && $node->class instanceof Node\Name
                    && $node->class->toString() === 'Schedule'
                    && $node->name instanceof Node\Identifier
                    && $node->name->toString() === 'command'
                ) {
                    if (count($node->args) > 0 && $node->args[0]->value instanceof ClassConstFetch) {
                        $classArg = $node->args[0]->value;
                    }
                } elseif ($node instanceof MethodCall
                    && $node->name instanceof Node\Identifier
                    && $node->name->toString() === 'command'
                ) {
                    if (count($node->args) > 0 && $node->args[0]->value instanceof ClassConstFetch) {
                        $classArg = $node->args[0]->value;
                    }
                }

                if ($classArg !== null && $classArg->class instanceof Node\Name) {
                    $this->commands[] = [
                        'line' => $node->getLine(),
                        'class' => $classArg->class->toString(),
                    ];
                }
            }
        };

        $this->traverse($stmts, $visitor);

        $commands = [];
        foreach ($visitor->commands as $cmdInfo) {
            $fqcn = $this->resolveFqcn($fileContent, $cmdInfo['class']);
            $commands[] = [
                'line' => $cmdInfo['line'],
                'class' => $fqcn ?? $cmdInfo['class'],
            ];
        }

        return $commands;
    }
}
