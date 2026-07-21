<?php

namespace SajjadHossain\Doctor\Checks\Routes;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class RouteClosureBreaksCacheCheck extends PhpAstCheck
{
    private array $scanPaths = [];

    private const ROUTE_METHODS = ['get', 'post', 'put', 'patch', 'delete', 'options', 'any', 'match'];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Route Closure';
    }

    public function category(): string
    {
        return 'routes';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: [base_path('routes')];

        foreach ($this->scanPhpFiles($paths) as $file) {
            $stmts = $this->parse($file['content']);
            if ($stmts === null) {
                continue;
            }

            $visitor = new class(self::ROUTE_METHODS) extends NodeVisitorAbstract {
                private array $routeMethods;
                public array $issues = [];

                public function __construct(array $routeMethods)
                {
                    $this->routeMethods = $routeMethods;
                }

                public function enterNode(Node $node): void
                {
                    if (!$node instanceof StaticCall) {
                        return;
                    }

                    $class = $node->class;
                    if (!$class instanceof Node\Name) {
                        return;
                    }

                    $className = $class->toString();
                    if (!in_array($className, ['Route', '\\Route', 'Illuminate\Support\Facades\Route'], true)) {
                        return;
                    }

                    $method = $node->name->toString();
                    if (!in_array($method, $this->routeMethods, true)) {
                        return;
                    }

                    $hasClosure = false;
                    foreach ($node->args as $arg) {
                        if ($arg->value instanceof Closure || $arg->value instanceof ArrowFunction) {
                            $hasClosure = true;
                            break;
                        }
                    }

                    if (!$hasClosure) {
                        return;
                    }

                    $uri = '(dynamic)';
                    if ($method === 'match' && isset($node->args[1]) && $node->args[1]->value instanceof Node\Scalar\String_) {
                        $uri = $node->args[1]->value->value;
                    } elseif ($method !== 'match' && isset($node->args[0]) && $node->args[0]->value instanceof Node\Scalar\String_) {
                        $uri = $node->args[0]->value->value;
                    }

                    $this->issues[] = [
                        'line' => $node->getLine(),
                        'method' => $method,
                        'uri' => $uri,
                    ];
                }
            };

            $this->traverse($stmts, $visitor);

            foreach ($visitor->issues as $issue) {
                $locations[] = [
                    'file' => $file['path'],
                    'line' => $issue['line'],
                    'method' => $issue['method'],
                    'uri' => $issue['uri'],
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No route closures found.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' route(s) defined with closures — closures cannot be cached by `php artisan route:cache`.',
            locations: $locations,
            suggestion: 'Replace closures with invokable controller classes to enable route caching.',
        );
    }
}
