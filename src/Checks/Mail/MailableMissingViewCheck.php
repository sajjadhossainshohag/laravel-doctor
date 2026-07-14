<?php

namespace SajjadHossain\Doctor\Checks\Mail;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\PhpAstCheck;

class MailableMissingViewCheck extends PhpAstCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Mailable References Missing View';
    }

    public function category(): string
    {
        return 'mail';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: [app_path('Mail')];

        $viewMethods = ['view', 'text', 'markdown'];

        foreach ($this->scanPhpFiles($paths) as $file) {
            $stmts = $this->parse($this->stripComments($file['content']));
            if ($stmts === null) {
                continue;
            }

            $viewNames = [];

            $visitor = new class($viewMethods) extends NodeVisitorAbstract {
                private array $methods;
                public array $viewNames = [];

                public function __construct(array $methods) { $this->methods = $methods; }

                public function enterNode(Node $node): void
                {
                    // ->view('name'), ->text('name'), ->markdown('name')
                    if ($node instanceof MethodCall
                        && $node->name instanceof Node\Identifier
                        && in_array($node->name->toString(), $this->methods, true)
                        && count($node->args) > 0
                        && $node->args[0]->value instanceof String_
                    ) {
                        $this->viewNames[] = $node->args[0]->value->value;
                        return;
                    }

                    // new Content(view: 'name', ...) or new Content(['view' => 'name'])
                    if (!$node instanceof New_ || !$node->class instanceof Node\Name) {
                        return;
                    }
                    $parts = explode('\\', $node->class->toString());
                    $shortName = end($parts);
                    if ($shortName !== 'Content') {
                        return;
                    }

                    foreach ($node->args as $arg) {
                        if (!$arg instanceof Arg) {
                            continue;
                        }

                        // Named arg: new Content(view: 'name')
                        if ($arg->name !== null
                            && $arg->name instanceof Node\Identifier
                            && in_array($arg->name->toString(), $this->methods, true)
                            && $arg->value instanceof String_
                        ) {
                            $this->viewNames[] = $arg->value->value;
                            continue;
                        }

                        // Array form: new Content(['view' => 'name'])
                        if ($arg->name === null && $arg->value instanceof Array_) {
                            foreach ($arg->value->items as $item) {
                                if ($item instanceof ArrayItem
                                    && $item->key instanceof String_
                                    && in_array($item->key->value, $this->methods, true)
                                    && $item->value instanceof String_
                                ) {
                                    $this->viewNames[] = $item->value->value;
                                }
                            }
                        }
                    }
                }
            };

            $this->traverse($stmts, $visitor);

            foreach (array_unique($visitor->viewNames) as $viewName) {
                if (! view()->exists($viewName)) {
                    $locations[] = [
                        'file' => $file['path'],
                        'view' => $viewName,
                        'issue' => "Mailable references view '{$viewName}' which does not exist",
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
                message: 'All mailable views exist.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' mailable view reference(s) could not be resolved.',
            locations: $locations,
            suggestion: 'Create the missing view or fix the view reference in the mailable.',
        );
    }
}
