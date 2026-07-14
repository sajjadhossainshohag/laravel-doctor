<?php

namespace SajjadHossain\Doctor\Checks\Blade;

use Illuminate\Support\Facades\Route;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\BladeAstCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class MissingNamedRoutesCheck extends BladeAstCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Blade Route & URL Issues';
    }

    public function category(): string
    {
        return 'scan';
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $routes = Route::getRoutes();
        $locations = [];
        $scanned = 0;

        $paths = $this->scanPaths ?: $this->viewPaths();

        foreach ($this->scanPhpFiles($paths) as $file) {
            $raw = $this->stripComments($file['content']);
            $stmts = $this->parseBlade($raw);
            if ($stmts === null) {
                continue;
            }

            $directiveLines = $this->mapDirectiveLines($raw, 'route'); // won't catch all, but for Blade we search route() calls

            $visitor = new class extends NodeVisitorAbstract {
                public array $routeCalls = [];

                public function enterNode(Node $node): void
                {
                    if ($node instanceof FuncCall
                        && $node->name instanceof Node\Name
                        && $node->name->toString() === 'route'
                        && count($node->args) > 0
                        && $node->args[0]->value instanceof String_
                    ) {
                        $this->routeCalls[] = [
                            'line' => $node->getLine(),
                            'name' => $node->args[0]->value->value,
                        ];
                    }
                }
            };

            $this->traverse($stmts, $visitor);

            foreach ($visitor->routeCalls as $call) {
                $scanned++;
                $name = $call['name'];

                if (str_starts_with($name, '__')) {
                    continue;
                }

                if (str_contains($name, '..') || str_starts_with($name, '.') || str_ends_with($name, '.')) {
                    $locations[] = [
                        'file' => $file['path'],
                        'line' => $call['line'],
                        'issue' => 'invalid route name format',
                        'value' => $name,
                    ];
                    continue;
                }

                if ($routes->getByName($name) === null) {
                    $locations[] = [
                        'file' => $file['path'],
                        'line' => $call['line'],
                        'issue' => 'undefined named route',
                        'value' => $name,
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
                message: "All {$scanned} route() references look correct.",
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' route reference(s) with issues.',
            locations: $locations,
            suggestion: 'Define missing named routes or fix invalid route names.',
        );
    }
}
