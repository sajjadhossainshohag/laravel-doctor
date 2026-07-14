<?php

namespace SajjadHossain\Doctor\Checks\Schedule;

use Illuminate\Contracts\Console\Kernel;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class DeletedScheduledCommandCheck extends PhpAstCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Scheduled Command Name No Longer Registered';
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

        $artisanCommands = [];
        try {
            $artisan = app(Kernel::class);
            $artisanCommands = array_keys($artisan->all());
        } catch (\Throwable) {
        }

        $paths = $this->scanPaths ?: [app_path('Console'), app_path('Providers')];

        foreach ($this->scanPhpFiles($paths) as $file) {
            $stmts = $this->parse($file['content']);
            if ($stmts === null) {
                continue;
            }

            $visitor = new class extends NodeVisitorAbstract {
                public array $commands = [];

                public function enterNode(Node $node): void
                {
                    $stringArg = null;

                    if ($node instanceof StaticCall
                        && $node->class instanceof Node\Name
                        && $node->class->toString() === 'Schedule'
                        && $node->name instanceof Node\Identifier
                        && $node->name->toString() === 'command'
                    ) {
                        if (count($node->args) > 0 && $node->args[0]->value instanceof String_) {
                            $stringArg = $node->args[0]->value->value;
                        }
                    } elseif ($node instanceof MethodCall
                        && $node->name instanceof Node\Identifier
                        && $node->name->toString() === 'command'
                    ) {
                        if (count($node->args) > 0 && $node->args[0]->value instanceof String_) {
                            $stringArg = $node->args[0]->value->value;
                        }
                    }

                    if ($stringArg !== null) {
                        $this->commands[] = [
                            'line' => $node->getLine(),
                            'name' => $stringArg,
                        ];
                    }
                }
            };

            $this->traverse($stmts, $visitor);

            foreach ($visitor->commands as $cmdInfo) {
                if (! empty($artisanCommands) && ! in_array($cmdInfo['name'], $artisanCommands, true)) {
                    $locations[] = [
                        'file' => $file['path'],
                        'line' => $cmdInfo['line'],
                        'issue' => "Scheduled command '{$cmdInfo['name']}' is not registered in Artisan",
                    ];
                }
            }
        }

        $consoleRoute = base_path('routes/console.php');
        if (is_file($consoleRoute)) {
            $content = file_get_contents($consoleRoute);
            $stmts = $this->parse($content);
            if ($stmts !== null) {
                $visitor = new class extends NodeVisitorAbstract {
                    public array $commands = [];
                    public function enterNode(Node $node): void
                    {
                        $stringArg = null;
                        if ($node instanceof StaticCall
                            && $node->class instanceof Node\Name
                            && $node->class->toString() === 'Schedule'
                            && $node->name instanceof Node\Identifier
                            && $node->name->toString() === 'command'
                        ) {
                            if (count($node->args) > 0 && $node->args[0]->value instanceof String_) {
                                $stringArg = $node->args[0]->value->value;
                            }
                        } elseif ($node instanceof MethodCall
                            && $node->name instanceof Node\Identifier
                            && $node->name->toString() === 'command'
                        ) {
                            if (count($node->args) > 0 && $node->args[0]->value instanceof String_) {
                                $stringArg = $node->args[0]->value->value;
                            }
                        }

                        if ($stringArg !== null) {
                            $this->commands[] = [
                                'line' => $node->getLine(),
                                'name' => $stringArg,
                            ];
                        }
                    }
                };

                $this->traverse($stmts, $visitor);

                foreach ($visitor->commands as $cmdInfo) {
                    if (! empty($artisanCommands) && ! in_array($cmdInfo['name'], $artisanCommands, true)) {
                        $locations[] = [
                            'file' => $consoleRoute,
                            'line' => $cmdInfo['line'],
                            'issue' => "Scheduled command '{$cmdInfo['name']}' is not registered in Artisan",
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
                message: 'All scheduled command names are registered in Artisan.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' scheduled command name(s) not found in Artisan.',
            locations: $locations,
            suggestion: 'Register the command or fix the schedule reference.',
        );
    }
}
