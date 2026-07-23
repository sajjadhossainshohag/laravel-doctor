<?php

namespace SajjadHossain\Doctor\Checks\Eloquent;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class AccessorMutatorStyleConflictCheck extends PhpAstCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Accessor/Mutator Style Conflict';
    }

    public function category(): string
    {
        return 'eloquent';
    }

    public function severity(): Severity
    {
        return Severity::Info;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: [app_path('Models')];

        foreach ($this->scanPhpFiles($paths) as $file) {
            $stmts = $this->parse($file['content']);
            if ($stmts === null) {
                continue;
            }

            $fqcn = $this->resolveFqcn($file['content'], $this->extractShortClassName($file['content']), $stmts);
            if ($fqcn === null || !class_exists($fqcn)) {
                continue;
            }

            try {
                $reflection = new \ReflectionClass($fqcn);
            } catch (\Throwable) {
                continue;
            }
            if ($reflection->isAbstract() || $reflection->isTrait()) {
                continue;
            }

            $oldAccessors = [];
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $fqcn) {
                    continue;
                }
                $name = $method->getName();
                if (preg_match('/^get(\w+)Attribute$/', $name, $am)) {
                    $attr = lcfirst($am[1]);
                    $oldAccessors[$attr] = ($oldAccessors[$attr] ?? '') . 'get';
                } elseif (preg_match('/^set(\w+)Attribute$/', $name, $sm)) {
                    $attr = lcfirst($sm[1]);
                    $oldAccessors[$attr] = ($oldAccessors[$attr] ?? '') . 'set';
                }
            }

            $visitor = new class extends NodeVisitorAbstract {
                public array $makeCalls = [];
                private ?string $currentMethod = null;

                public function enterNode(Node $node): void
                {
                    if ($node instanceof ClassMethod && $node->name instanceof Node\Identifier) {
                        $this->currentMethod = $node->name->toString();
                    }

                    if ($node instanceof StaticCall
                        && $node->class instanceof Node\Name
                        && substr($node->class->toString(), strrpos($node->class->toString(), '\\') ?: 0) === 'Attribute'
                        && $node->name instanceof Node\Identifier
                        && $node->name->toString() === 'make'
                    ) {
                        $flags = '';
                        foreach ($node->args as $arg) {
                            if ($arg->name instanceof Node\Identifier) {
                                if ($arg->name->toString() === 'get') { $flags .= 'g'; }
                                if ($arg->name->toString() === 'set') { $flags .= 's'; }
                            }
                        }
                        if ($flags !== '' && $this->currentMethod !== null) {
                            $this->makeCalls[] = ['flags' => $flags, 'method' => $this->currentMethod];
                        }
                    }
                }
            };

            $this->traverse($stmts, $visitor);

            $newAccessors = [];
            foreach ($visitor->makeCalls as $call) {
                if (preg_match('/^(get|set)?(\w+)(Attribute)?$/', $call['method'], $m)) {
                    $attrName = lcfirst($m[2]);
                    $newAccessors[$attrName] = ($newAccessors[$attrName] ?? '') . $call['flags'];
                }
            }

            $conflicts = [];
            foreach ($oldAccessors as $attr => $flags) {
                $newFlags = $newAccessors[$attr] ?? '';
                if ($newFlags !== '') {
                    $conflicts[] = $attr . ' (old: ' . implode('+', str_split($flags)) . ', new: ' . implode('+', str_split($newFlags)) . ')';
                }
            }

            if (!empty($conflicts)) {
                $locations[] = [
                    'file' => $file['path'],
                    'issue' => 'Model declares old-style getXxxAttribute/setXxxAttribute methods AND new-style Attribute::make() bindings for the same attribute(s): ' . implode(', ', $conflicts),
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No accessor/mutator style conflicts on the same attribute detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' model(s) define old and new accessor/mutator styles for the same attribute.',
            locations: $locations,
            suggestion: 'Pick one style per attribute. Mixing styles across different attributes is fine.',
        );
    }

    private function extractShortClassName(string $content): string
    {
        if (preg_match('/^\s*(?:final\s+|abstract\s+)?(?:readonly\s+)?class\s+(\w+)/m', $content, $m)) {
            return $m[1];
        }
        return '';
    }
}
