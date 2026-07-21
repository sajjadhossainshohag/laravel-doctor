<?php

namespace SajjadHossain\Doctor\Checks\Config;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\Concerns\ResolvesConfigAliases;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class NonExistentConfigFileCheck extends PhpAstCheck
{
    use ResolvesConfigAliases;

    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'config() / Config::get() References Non-Existent Config File';
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

        foreach ($this->scanPhpFiles($paths) as $file) {
            $stmts = $this->parse($this->stripComments($file['content']));
            if ($stmts === null) {
                continue;
            }

            $configAliases = $this->resolveConfigAliases($file['content']);

            $visitor = new class ($configAliases) extends NodeVisitorAbstract {
                private array $configAliases;
                public array $issues = [];

                public function __construct(array $configAliases)
                {
                    $this->configAliases = $configAliases;
                }

                public function enterNode(Node $node): void
                {
                    $key = null;
                    $line = null;

                    if ($node instanceof FuncCall && $node->name instanceof Node\Name) {
                        $parts = explode('\\', $node->name->toString());
                        $name = end($parts);
                        if (
                            $name === 'config'
                            && count($node->args) > 0
                            && $node->args[0]->value instanceof String_
                        ) {
                            $key = $node->args[0]->value->value;
                            $line = $node->getLine();
                        }
                    } elseif (
                        $node instanceof StaticCall
                        && $node->name instanceof Node\Identifier
                        && $node->name->toString() === 'get'
                        && $node->class instanceof Node\Name
                    ) {
                        $className = ltrim($node->class->toString(), '\\');
                        if (
                            in_array($className, $this->configAliases, true)
                            && count($node->args) > 0
                            && $node->args[0]->value instanceof String_
                        ) {
                            $key = $node->args[0]->value->value;
                            $line = $node->getLine();
                        }
                    }

                    if ($key === null) {
                        return;
                    }

                    if (count($node->args) > 1) {
                        return;
                    }

                    $segments = explode('.', $key);
                    if (count($segments) < 2) {
                        return;
                    }

                    if (str_contains($key, '::')) {
                        return;
                    }

                    $configFile = $segments[0];
                    $configPath = config_path($configFile . '.php');

                    if (!file_exists($configPath) && config($configFile) === null) {
                        $this->issues[] = [
                            'line' => $line,
                            'configFile' => $configFile,
                        ];
                    }
                }
            };

            $this->traverse($stmts, $visitor);

            foreach ($visitor->issues as $issue) {
                $locations[] = [
                    'file' => $file['path'],
                    'line' => $issue['line'],
                    'issue' => "config file `{$issue['configFile']}.php` does not exist.",
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No references to non-existent config files.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' config file(s) referenced but do not exist.',
            locations: $locations,
            suggestion: 'Create the missing config file or fix the config key reference.',
        );
    }

}
