<?php

namespace SajjadHossain\Doctor\Checks\Security;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class RequestAllInCreateCheck extends PhpAstCheck
{
    private const MASS_ASSIGNMENT_METHODS = [
        'create',
        'forceCreate',
        'createOrFail',
        'createOrFirst',
        'createQuietly',
        'update',
        'forceUpdate',
        'updateOrFail',
        'firstOrCreate',
        'updateOrCreate',
        'firstOrNew',
    ];

    private const DANGEROUS_INPUT_METHODS = [
        'all',
        'input',
        'post',
        'query',
    ];

    private const REQUEST_TARGETS = [
        'Illuminate\\Http\\Request',
        'Illuminate\\Support\\Facades\\Request',
    ];

    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Raw request input in mass-assignment method';
    }

    public function category(): string
    {
        return 'security';
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: config('doctor.scan_paths', [app_path()]);

        foreach ($this->scanPhpFiles($paths) as $file) {
            $stmts = $this->parse($file['content']);
            if ($stmts === null) {
                continue;
            }

            $requestAliases = $this->resolveRequestAliases($file['content']);

            $visitor = new class (self::MASS_ASSIGNMENT_METHODS, self::DANGEROUS_INPUT_METHODS, $requestAliases) extends NodeVisitorAbstract {
                public array $issues = [];
                private array $massAssignmentMethods;
                private array $dangerousInputMethods;
                private array $requestAliases;

                public function __construct(array $massAssignmentMethods, array $dangerousInputMethods, array $requestAliases)
                {
                    $this->massAssignmentMethods = $massAssignmentMethods;
                    $this->dangerousInputMethods = $dangerousInputMethods;
                    $this->requestAliases = $requestAliases;
                }

                public function enterNode(Node $node): void
                {
                    if (!$node instanceof StaticCall && !$node instanceof MethodCall) {
                        return;
                    }

                    if (
                        !($node->name instanceof Node\Identifier)
                        || !in_array($node->name->toString(), $this->massAssignmentMethods, true)
                    ) {
                        return;
                    }

                    foreach ($node->args as $arg) {
                        if ($this->isDangerousInputCall($arg->value)) {
                            $this->issues[] = ['line' => $node->getLine()];
                            return;
                        }
                    }
                }

                private function isDangerousInputCall(Node $node): bool
                {
                    if ($node instanceof MethodCall) {
                        if (
                            $node->name instanceof Node\Identifier
                            && in_array($node->name->toString(), $this->dangerousInputMethods, true)
                            && count($node->args) === 0
                            && !($node->var instanceof MethodCall)
                            && !($node->var instanceof StaticCall)
                        ) {
                            return true;
                        }
                    }

                    if ($node instanceof StaticCall) {
                        if (
                            $node->name instanceof Node\Identifier
                            && in_array($node->name->toString(), $this->dangerousInputMethods, true)
                            && count($node->args) === 0
                            && $node->class instanceof Node\Name
                        ) {
                            $className = ltrim($node->class->toString(), '\\');
                            if (in_array($className, $this->requestAliases, true)) {
                                return true;
                            }
                        }
                    }

                    return false;
                }
            };

            $this->traverse($stmts, $visitor);

            foreach ($visitor->issues as $issue) {
                $locations[] = [
                    'file' => $file['path'],
                    'line' => $issue['line'],
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No mass-assignment via raw request input detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: sprintf(
                'Found %d mass-assignment(s) using raw request input without validation.',
                count($locations)
            ),
            locations: $locations,
            suggestion: 'Replace $request->all()/input()/post()/query() with a validated subset via FormRequest, $request->validated(), or $request->only(...).',
        );
    }

    private function resolveRequestAliases(string $content): array
    {
        $aliases = ['Request'];

        if (preg_match_all('/^\s*use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/m', $content, $uses, PREG_SET_ORDER)) {
            foreach ($uses as $useMatch) {
                $fqcn = ltrim($useMatch[1], '\\');
                if (in_array($fqcn, self::REQUEST_TARGETS, true)) {
                    $alias = $useMatch[2] ?? substr($fqcn, strrpos($fqcn, '\\') + 1);
                    $aliases[] = $alias;
                    $aliases[] = $fqcn;
                }
            }
        }

        return array_unique($aliases);
    }
}
