<?php

namespace SajjadHossain\Doctor\Checks\Eloquent;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class MissingGuardedOrFillableCheck extends PhpAstCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Missing $guarded or $fillable';
    }

    public function category(): string
    {
        return 'eloquent';
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: [app_path('Models')];

        foreach ($this->scanPhpFiles($paths) as $file) {
            $stmts = $this->parse($this->stripComments($file['content']));
            if ($stmts === null) {
                continue;
            }

            $visitor = new class($file['content']) extends NodeVisitorAbstract {
                private string $fileContent;
                private bool $inModel = false;
                private bool $hasFillable = false;
                private bool $hasGuarded = false;
                private bool $hasGuardedAttr = false;
                private bool $hasUnguardedAttr = false;
                private ?string $modelName = null;
                public array $models = [];

                public function __construct(string $fileContent) { $this->fileContent = $fileContent; }

                public function enterNode(Node $node): void
                {
                    if ($node instanceof Class_) {
                        if ($node->extends instanceof Node\Name) {
                            $parts = explode('\\', $node->extends->toString());
                            $baseName = end($parts);
                            if ($baseName !== 'Model') {
                                return;
                            }
                        } else {
                            return;
                        }
                        if ($node->isAbstract()) {
                            return;
                        }

                        $this->inModel = true;
                        $this->modelName = $node->name instanceof Node\Identifier ? $node->name->toString() : null;
                        $this->hasFillable = false;
                        $this->hasGuarded = false;
                        $this->hasGuardedAttr = false;
                        $this->hasUnguardedAttr = false;

                        foreach ($node->attrGroups as $attrGroup) {
                            foreach ($attrGroup->attrs as $attr) {
                                if ($attr->name instanceof Node\Name) {
                                    $name = $attr->name->toString();
                                    if ($name === 'Guarded') { $this->hasGuardedAttr = true; }
                                    if ($name === 'Unguarded') { $this->hasUnguardedAttr = true; }
                                }
                            }
                        }
                        return;
                    }

                    if (!$this->inModel) {
                        return;
                    }

                    // Track properties
                    if ($node instanceof Property) {
                        if ($node->isStatic()) {
                            return;
                        }
                        $propName = null;
                        if (!empty($node->props)) {
                            $prop = $node->props[0];
                            if ($prop->name instanceof Node\VarLikeIdentifier) {
                                $propName = $prop->name->toString();
                            }
                        }
                        if ($propName === 'fillable' && $prop->default !== null) {
                            $this->hasFillable = true;
                        }
                        if ($propName === 'guarded' && $prop->default !== null) {
                            $this->hasGuarded = true;
                        }
                        return;
                    }

                    // When we leave the class, evaluate
                    if ($node === null) {
                        return;
                    }
                }

                public function leaveNode(Node $node): void
                {
                    if ($node instanceof Class_ && $this->inModel) {
                        $this->inModel = false;

                        if ($this->hasFillable || $this->hasGuarded || $this->hasGuardedAttr || $this->hasUnguardedAttr) {
                            return;
                        }

                        if ($this->modelName !== null && $this->modelName !== '') {
                            $fqcn = $this->resolveFqcnHelper($this->fileContent, $this->modelName);
                            if ($fqcn !== null && $this->inheritsFillableOrGuarded($fqcn)) {
                                return;
                            }
                        }

                        $this->models[] = [
                            'name' => $this->modelName ?? 'unknown',
                            'line' => $node->getLine(),
                        ];
                    }
                }

                private function resolveFqcnHelper(string $content, string $className): ?string
                {
                    if (preg_match('/^\s*namespace\s+([\w\\\\]+);/m', $content, $ns)) {
                        return $ns[1] . '\\' . $className;
                    }
                    return null;
                }

                private function inheritsFillableOrGuarded(string $fqcn): bool
                {
                    if (!class_exists($fqcn)) {
                        return false;
                    }
                    try {
                        $ref = new \ReflectionClass($fqcn);
                        $current = $ref;
                        while ($current) {
                            if (str_starts_with($current->getName(), 'Illuminate\\')) {
                                break;
                            }
                            foreach (['fillable', 'guarded'] as $prop) {
                                if ($current->hasProperty($prop)) {
                                    $p = $current->getProperty($prop);
                                    if ($p->isStatic() || $p->getDeclaringClass()->getName() !== $current->getName()) {
                                        continue;
                                    }
                                    return true;
                                }
                            }
                            $current = $current->getParentClass();
                        }
                    } catch (\Throwable) {
                    }
                    return false;
                }
            };

            $this->traverse($stmts, $visitor);

            foreach ($visitor->models as $modelInfo) {
                $locations[] = [
                    'file' => $file['path'],
                    'line' => $modelInfo['line'],
                    'issue' => "Model '{$modelInfo['name']}' declares neither \$fillable nor \$guarded — mass-assignment behaviour is implicit and likely unintended",
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All models have appropriate mass-assignment protection.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' model(s) have unsafe mass-assignment configuration.',
            locations: $locations,
            suggestion: 'Add `protected $fillable = [...]` to limit which attributes can be mass-assigned.',
        );
    }
}
