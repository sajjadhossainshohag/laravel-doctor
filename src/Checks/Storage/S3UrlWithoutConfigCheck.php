<?php

namespace SajjadHossain\Doctor\Checks\Storage;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class S3UrlWithoutConfigCheck extends PhpAstCheck
{
    public function name(): string
    {
        return 'S3 URL Called Without Config';
    }

    public function category(): string
    {
        return 'storage';
    }

    public function severity(): Severity
    {
        return Severity::Info;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = config('doctor.scan_paths', [app_path(), resource_path('views')]);

        $s3Config = config('filesystems.disks.s3');
        $hasBucket = ! empty($s3Config['bucket']);
        $hasRegion = ! empty($s3Config['region']);

        foreach ($this->scanPhpFiles($paths) as $file) {
            $stmts = $this->parse($this->stripComments($file['content']));
            if ($stmts === null) {
                continue;
            }

            $visitor = new class extends NodeVisitorAbstract {
                public array $calls = [];
                public function enterNode(Node $node): void {
                    // Storage::disk('s3')->url(...)
                    if ($node instanceof MethodCall
                        && $node->name instanceof Node\Identifier
                        && $node->name->toString() === 'url'
                        && $node->var instanceof StaticCall
                        && $node->var->class instanceof Node\Name
                        && $node->var->class->toString() === 'Storage'
                        && $node->var->name instanceof Node\Identifier
                        && $node->var->name->toString() === 'disk'
                    ) {
                        if (count($node->var->args) > 0
                            && $node->var->args[0]->value instanceof String_
                            && $node->var->args[0]->value->value === 's3'
                        ) {
                            $this->calls[] = $node->getLine();
                        }
                    }
                }
            };

            $this->traverse($stmts, $visitor);

            foreach ($visitor->calls as $line) {
                if (! $hasBucket || ! $hasRegion) {
                    $missing = [];
                    if (! $hasBucket) { $missing[] = 'bucket'; }
                    if (! $hasRegion) { $missing[] = 'region'; }
                    $locations[] = [
                        'file' => $file['path'],
                        'line' => $line,
                        'issue' => "Storage::disk('s3')->url() called but S3 is missing required config: ".implode(', ', $missing),
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
                message: 'All S3 URL calls have the required bucket and region configured.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' S3 url() call(s) without required bucket/region.',
            locations: $locations,
            suggestion: 'Set the s3.bucket and s3.region values in config/filesystems.php.',
        );
    }
}
