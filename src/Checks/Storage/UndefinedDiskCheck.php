<?php

namespace SajjadHossain\Doctor\Checks\Storage;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class UndefinedDiskCheck extends PhpAstCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Storage::disk() References Undefined Disk';
    }

    public function category(): string
    {
        return 'storage';
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: config('doctor.scan_paths', [app_path(), resource_path('views')]);
        $disks = array_keys(config('filesystems.disks', []));

        foreach ($this->scanPhpFiles($paths) as $file) {
            $stmts = $this->parse($this->stripComments($file['content']));
            if ($stmts === null) {
                continue;
            }

            $visitor = new class($disks) extends NodeVisitorAbstract {
                private array $disks;
                public array $issues = [];

                public function __construct(array $disks) { $this->disks = $disks; }

                public function enterNode(Node $node): void
                {
                    // Storage::disk('name')
                    if ($node instanceof StaticCall
                        && $node->class instanceof Node\Name
                        && $node->class->toString() === 'Storage'
                        && $node->name instanceof Node\Identifier
                        && $node->name->toString() === 'disk'
                        && count($node->args) > 0
                        && $node->args[0]->value instanceof String_
                    ) {
                        $diskName = $node->args[0]->value->value;
                        if (!in_array($diskName, $this->disks, true)) {
                            $this->issues[] = [
                                'line' => $node->getLine(),
                                'disk' => $diskName,
                            ];
                        }
                    }
                }
            };

            $this->traverse($stmts, $visitor);

            foreach ($visitor->issues as $issue) {
                $locations[] = [
                    'file' => $file['path'],
                    'line' => $issue['line'],
                    'issue' => "Storage::disk('{$issue['disk']}') — '{$issue['disk']}' is not defined in config/filesystems.php",
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All Storage::disk() calls reference defined disks.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' Storage::disk() call(s) reference undefined disk(s).',
            locations: $locations,
            suggestion: 'Define the disk in config/filesystems.php or fix the disk name.',
        );
    }
}
