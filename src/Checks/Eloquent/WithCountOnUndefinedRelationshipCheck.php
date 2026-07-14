<?php

namespace SajjadHossain\Doctor\Checks\Eloquent;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class WithCountOnUndefinedRelationshipCheck extends PhpAstCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'withCount() on Undefined Relationships';
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
        $paths = $this->scanPaths ?: [app_path('Models'), app_path('Http/Controllers')];

        foreach ($this->scanPhpFiles($paths) as $file) {
            $stmts = $this->parse($this->stripComments($file['content']));
            if ($stmts === null) {
                continue;
            }

            $modelImports = $file['content'] ? $this->findModelImports($file['content']) : [];

            $visitor = new class extends NodeVisitorAbstract {
                public array $calls = [];

                public function enterNode(Node $node): void
                {
                    // ->withCount('rel') — chained form
                    if ($node instanceof MethodCall
                        && $node->name instanceof Node\Identifier
                        && $node->name->toString() === 'withCount'
                        && count($node->args) > 0
                    ) {
                        $arg = $node->args[0]->value;
                        // Single string: ->withCount('posts')
                        if ($arg instanceof String_) {
                            $rel = explode('.', $arg->value)[0];
                            $this->calls[] = [
                                'rel' => $rel,
                                'isStatic' => false,
                                'isArray' => false,
                                'line' => $node->getLine(),
                            ];
                        } elseif ($arg instanceof Array_) {
                            // Array form: ->withCount(['posts', 'comments'])
                            foreach ($arg->items as $item) {
                                if ($item instanceof ArrayItem && $item->value instanceof String_) {
                                    $entry = $item->value->value;
                                    $withoutAlias = trim((string) preg_replace('/\s+as\s+\w+$/i', '', $entry));
                                    $base = explode('.', $withoutAlias)[0];
                                    if ($base !== '') {
                                        $this->calls[] = [
                                            'rel' => $base,
                                            'isStatic' => false,
                                            'isArray' => true,
                                            'line' => $node->getLine(),
                                        ];
                                    }
                                }
                            }
                        }
                        return;
                    }

                    // Model::withCount('rel') — static form
                    if ($node instanceof StaticCall
                        && $node->class instanceof Node\Name
                        && $node->name instanceof Node\Identifier
                        && $node->name->toString() === 'withCount'
                        && count($node->args) > 0
                        && $node->args[0]->value instanceof String_
                    ) {
                        $rel = explode('.', $node->args[0]->value->value)[0];
                        $this->calls[] = [
                            'rel' => $rel,
                            'isStatic' => true,
                            'isArray' => false,
                            'line' => $node->getLine(),
                            'className' => $node->class->toString(),
                        ];
                    }
                }
            };

            $this->traverse($stmts, $visitor);

            foreach ($visitor->calls as $call) {
                $modelClass = $this->guessModelClassFromAst(
                    $file['content'],
                    $modelImports,
                    $call['isStatic'],
                    $call['className'] ?? null
                );

                if ($modelClass === null) {
                    continue;
                }

                $candidates = (array) $modelClass;
                $exists = false;
                foreach ($candidates as $candidate) {
                    if ($this->relationshipExists($candidate, $call['rel'])) {
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    $modelLabel = is_array($modelClass) ? implode('|', $modelClass) : $modelClass;
                    $locations[] = [
                        'file' => $file['path'],
                        'line' => $call['line'],
                        'issue' => "withCount('{$call['rel']}') called but no candidate model ({$modelLabel}) declares a '{$call['rel']}' relationship",
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
                message: 'No suspicious withCount() calls detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' potential withCount() issue(s) detected.',
            locations: $locations,
            suggestion: 'Verify the relationship name exists on the model and withCount() is called before access.',
        );
    }

    private function findModelImports(string $content): array
    {
        $models = [];
        if (preg_match_all('/^use\s+([\w\\\\]+)\s*;/m', $content, $uses)) {
            foreach ($uses[1] as $fqcn) {
                if (class_exists($fqcn) && is_subclass_of($fqcn, 'Illuminate\Database\Eloquent\Model')) {
                    $models[] = ltrim($fqcn, '\\');
                }
            }
        }
        return $models;
    }

    private function guessModelClassFromAst(
        string $content,
        array $modelImports,
        bool $isStatic,
        ?string $staticClassName
    ): string|array|null {
        if ($isStatic && $staticClassName !== null) {
            $fqcn = $this->resolveFqcn($content, $staticClassName);
            if ($fqcn && class_exists($fqcn) && is_subclass_of($fqcn, 'Illuminate\Database\Eloquent\Model')) {
                return $fqcn;
            }
            return null;
        }

        if (preg_match_all('/\b(\w+)\s*::\s*(?:query|with|where|orderBy|select)\s*\([^)]*\)\s*->\s*withCount\s*\(/', $content, $staticCalls)) {
            $candidates = [];
            foreach ($staticCalls[1] as $shortName) {
                $fqcn = $this->resolveFqcn($content, $shortName);
                if ($fqcn && class_exists($fqcn) && is_subclass_of($fqcn, 'Illuminate\Database\Eloquent\Model')) {
                    $candidates[] = $fqcn;
                }
            }
            if (! empty($candidates)) {
                return array_values(array_unique($candidates));
            }
        }

        return null;
    }

    private function relationshipExists(string $modelClass, string $relation): bool
    {
        if (! class_exists($modelClass)) {
            return false;
        }
        try {
            $ref = new \ReflectionClass($modelClass);
            foreach ($ref->getMethods() as $method) {
                if ($method->getName() === $relation) {
                    return true;
                }
            }
        } catch (\Throwable) {
        }

        return false;
    }
}
