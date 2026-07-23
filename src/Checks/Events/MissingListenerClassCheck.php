<?php

namespace SajjadHossain\Doctor\Checks\Events;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class MissingListenerClassCheck extends PhpAstCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Missing Listener Class';
    }

    public function category(): string
    {
        return 'events';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: [app_path('Providers')];

        foreach ($this->scanPhpFiles($paths) as $file) {
            $stmts = $this->parse($file['content']);
            if ($stmts === null) {
                continue;
            }

            $visitor = new class($file['content']) extends NodeVisitorAbstract {
                private string $fileContent;
                public array $listeners = [];

                public function __construct(string $fileContent) { $this->fileContent = $fileContent; }

                public function enterNode(Node $node): void
                {
                    $this->collectFromArray($node);
                    $this->collectFromMethodCalls($node);
                }

                private function collectFromArray(Node $node): void
                {
                    // Find $listen property with array value
                    if (!$node instanceof Property
                        || !$node->props[0]->name instanceof Node\Identifier
                        || $node->props[0]->name->toString() !== 'listen'
                    ) {
                        return;
                    }

                    $propValue = $node->props[0]->default;
                    if (!$propValue instanceof Array_) {
                        return;
                    }

                    foreach ($propValue->items as $item) {
                        if ($item === null) {
                            continue;
                        }
                        // Value could be: Single listener (ClassConstFetch or String_)
                        // or array of listeners
                        $this->extractListenerClasses($item->value, $item->getLine());
                    }
                }

                private function collectFromMethodCalls(Node $node): void
                {
                    // Event::listen(FooListener::class, ...)
                    if ($node instanceof Node\Expr\StaticCall
                        && $node->class instanceof Node\Name
                        && ($node->class->toString() === 'Event' || str_ends_with($node->class->toString(), '\\Event'))
                        && $node->name instanceof Node\Identifier
                        && $node->name->toString() === 'listen'
                        && count($node->args) > 0
                    ) {
                        $this->findClassConstFetchInArgs($node->args, $node->getLine());
                    }
                }

                private function findClassConstFetchInArgs(array $args, int $line): void
                {
                    foreach ($args as $arg) {
                        if ($arg->value instanceof ClassConstFetch && $arg->value->class instanceof Node\Name) {
                            $this->listeners[] = [
                                'class' => $arg->value->class->toString(),
                                'line' => $line,
                            ];
                        } elseif ($arg->value instanceof String_) {
                            $this->listeners[] = [
                                'class' => ltrim($arg->value->value, '\\'),
                                'line' => $line,
                            ];
                        } elseif ($arg->value instanceof Array_) {
                            foreach ($arg->value->items as $arrItem) {
                                if ($arrItem !== null) {
                                    $this->findClassConstFetchInArgs([$arrItem], $line);
                                }
                            }
                        }
                    }
                }

                private function extractListenerClasses(Node $node, int $line): void
                {
                    if ($node instanceof ClassConstFetch && $node->class instanceof Node\Name) {
                        $this->listeners[] = [
                            'class' => $node->class->toString(),
                            'line' => $line,
                        ];
                    } elseif ($node instanceof String_) {
                        $this->listeners[] = [
                            'class' => ltrim($node->value, '\\'),
                            'line' => $line,
                        ];
                    } elseif ($node instanceof Array_) {
                        foreach ($node->items as $item) {
                            if ($item !== null) {
                                $this->extractListenerClasses($item->value, $line);
                            }
                        }
                    }
                }
            };

            $this->traverse($stmts, $visitor);

            foreach ($visitor->listeners as $listenerInfo) {
                $className = $listenerInfo['class'];
                $fqcn = $this->resolveFqcn($file['content'], $className);

                if ($fqcn !== null && class_exists($fqcn)) {
                    continue;
                }
                if (class_exists($className)) {
                    continue;
                }

                $locations[] = [
                    'file' => $file['path'],
                    'line' => $listenerInfo['line'],
                    'issue' => "Listener class '{$className}' does not exist",
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All registered listener classes exist.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' listener class(es) not found.',
            locations: $locations,
            suggestion: 'Create the missing listener class or remove the registration.',
        );
    }
}
