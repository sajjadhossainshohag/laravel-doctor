<?php

namespace SajjadHossain\Doctor\Checks\Jobs;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class MissingJobClassCheck extends PhpAstCheck
{
    public function name(): string
    {
        return 'Missing Job Classes';
    }

    public function category(): string
    {
        return 'jobs';
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = config('doctor.scan_paths', [app_path(), resource_path('views')]);

        $dispatchMethods = ['dispatch', 'dispatchIf', 'dispatchUnless'];

        foreach ($this->scanPhpFiles($paths) as $file) {
            $stmts = $this->parse($file['content']);
            if ($stmts === null) {
                continue;
            }

            $visitor = new class($dispatchMethods) extends NodeVisitorAbstract {
                private array $methods;
                public array $jobs = [];

                public function __construct(array $methods)
                {
                    $this->methods = $methods;
                }

                public function enterNode(Node $node): void
                {
                    if (!$node instanceof StaticCall) {
                        return;
                    }
                    if (!$node->class instanceof Node\Name) {
                        return;
                    }
                    if (!$node->name instanceof Node\Identifier) {
                        return;
                    }
                    if (!in_array($node->name->toString(), $this->methods, true)) {
                        return;
                    }

                    $className = $node->class->toString();
                    $skip = ['Bus', 'Queue', 'dispatch', 'event'];
                    if (in_array($className, $skip, true)) {
                        return;
                    }

                    $this->jobs[] = [
                        'line' => $node->getLine(),
                        'class' => $className,
                    ];
                }
            };

            $this->traverse($stmts, $visitor);

            foreach ($visitor->jobs as $jobInfo) {
                $fqcn = $this->resolveFqcn($file['content'], $jobInfo['class'], $stmts);
                $checkClass = $fqcn ?? $jobInfo['class'];

                if (!class_exists($checkClass)) {
                    $locations[] = [
                        'file' => $file['path'],
                        'line' => $jobInfo['line'],
                        'job' => $checkClass,
                        'issue' => 'Job class does not exist',
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
                message: 'All dispatched job classes exist.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' dispatched job class(es) not found.',
            locations: $locations,
            suggestion: 'Create the job class or fix the dispatch call.',
        );
    }
}
