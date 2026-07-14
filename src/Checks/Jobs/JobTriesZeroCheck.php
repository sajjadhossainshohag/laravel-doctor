<?php

namespace SajjadHossain\Doctor\Checks\Jobs;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class JobTriesZeroCheck extends PhpAstCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Job $tries Set to 0';
    }

    public function category(): string
    {
        return 'jobs';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: [app_path('Jobs')];

        foreach ($this->scanPhpFiles($paths) as $file) {
            $stmts = $this->parse($this->stripComments($file['content']));
            if ($stmts === null) {
                continue;
            }

            $visitor = new class extends NodeVisitorAbstract {
                private bool $inJobClass = false;
                private bool $hasTriesZero = false;
                private bool $hasRetryUntil = false;
                private bool $hasTriesMethod = false;
                private bool $hasBackoffProperty = false;
                private bool $hasBackoffMethod = false;
                private ?int $classLine = null;
                public array $jobs = [];

                public function enterNode(Node $node): void
                {
                    if ($node instanceof Class_) {
                        if ($node->isAbstract()) return;
                        $this->inJobClass = true;
                        $this->classLine = $node->getLine();
                        $this->hasTriesZero = false;
                        $this->hasRetryUntil = false;
                        $this->hasTriesMethod = false;
                        $this->hasBackoffProperty = false;
                        $this->hasBackoffMethod = false;
                        return;
                    }

                    if (!$this->inJobClass) {
                        return;
                    }

                    if ($node instanceof Property) {
                        if ($node->isStatic()) return;
                        $propName = null;
                        if (!empty($node->props)) {
                            $prop = $node->props[0];
                            if ($prop->name instanceof Node\VarLikeIdentifier) {
                                $propName = $prop->name->toString();
                            }
                        }

                        if ($propName === 'tries' && $prop->default instanceof Node\Scalar\LNumber && $prop->default->value === 0) {
                            $this->hasTriesZero = true;
                        }
                        if ($propName === 'backoff' && $prop->default !== null) {
                            $this->hasBackoffProperty = true;
                        }
                        return;
                    }

                    if ($node instanceof ClassMethod) {
                        if ($node->name instanceof Node\Identifier) {
                            $name = $node->name->toString();
                            if ($name === 'retryUntil') { $this->hasRetryUntil = true; }
                            if ($name === 'tries') { $this->hasTriesMethod = true; }
                            if ($name === 'backoff') { $this->hasBackoffMethod = true; }
                        }
                    }
                }

                public function leaveNode(Node $node): void
                {
                    if ($node instanceof Class_ && $this->inJobClass) {
                        $this->inJobClass = false;

                        if (!$this->hasTriesZero) {
                            return;
                        }
                        if ($this->hasRetryUntil || $this->hasTriesMethod) {
                            return;
                        }

                        $hasBackoff = $this->hasBackoffProperty || $this->hasBackoffMethod;
                        $message = 'Job $tries is set to 0 with no retryUntil()/tries() — job will retry forever, which is rarely intended';
                        if ($hasBackoff) {
                            $message .= ' (note: $backoff/backoff() is the delay between retries, not a retry cap — it does NOT stop the infinite loop)';
                        }

                        $this->jobs[] = [
                            'line' => $this->classLine ?? 0,
                            'issue' => $message,
                        ];
                    }
                }
            };

            $this->traverse($stmts, $visitor);

            foreach ($visitor->jobs as $jobInfo) {
                $locations[] = [
                    'file' => $file['path'],
                    'line' => $jobInfo['line'],
                    'issue' => $jobInfo['issue'],
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No jobs with $tries = 0 detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' job(s) with $tries = 0 and no retry cap.',
            locations: $locations,
            suggestion: 'Set $tries to a positive integer (e.g. 3), or add retryUntil()/tries() to cap the retries.',
        );
    }
}
