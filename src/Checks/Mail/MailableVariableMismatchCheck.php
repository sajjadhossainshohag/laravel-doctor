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

class MailableVariableMismatchCheck extends PhpAstCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Mailable Variable / Template Mismatch';
    }

    public function category(): string
    {
        return 'mail';
    }

    public function severity(): Severity
    {
        return Severity::Info;
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

            $withVars = [];
            $viewName = null;

            $visitor = new class($viewMethods) extends NodeVisitorAbstract {
                private array $methods;
                public array $withVars = [];
                public ?string $viewName = null;

                public function __construct(array $methods) { $this->methods = $methods; }

                public function enterNode(Node $node): void
                {
                    // ->with('name', $value) — single string key
                    if ($node instanceof MethodCall
                        && $node->name instanceof Node\Identifier
                        && $node->name->toString() === 'with'
                    ) {
                        if (count($node->args) >= 1 && $node->args[0]->value instanceof String_) {
                            $this->withVars[] = $node->args[0]->value->value;
                        }
                        // ->with(['name' => $value, ...])
                        if (count($node->args) >= 1 && $node->args[0]->value instanceof Array_) {
                            foreach ($node->args[0]->value->items as $item) {
                                if ($item instanceof ArrayItem && $item->key instanceof String_) {
                                    $this->withVars[] = $item->key->value;
                                }
                            }
                        }
                        return;
                    }

                    // ->view('name'), ->text('name'), ->markdown('name')
                    if ($node instanceof MethodCall
                        && $node->name instanceof Node\Identifier
                        && in_array($node->name->toString(), $this->methods, true)
                        && count($node->args) > 0
                        && $node->args[0]->value instanceof String_
                    ) {
                        $this->viewName = $node->args[0]->value->value;
                        return;
                    }

                    // new Content(view: 'name') or new Content(['view' => 'name'])
                    if (!$node instanceof New_ || !$node->class instanceof Node\Name) {
                        return;
                    }
                    $parts = explode('\\', $node->class->toString());
                    if (end($parts) !== 'Content') {
                        return;
                    }

                    foreach ($node->args as $arg) {
                        if (!$arg instanceof Arg) {
                            continue;
                        }

                        if ($arg->name !== null
                            && $arg->name instanceof Node\Identifier
                            && in_array($arg->name->toString(), $this->methods, true)
                            && $arg->value instanceof String_
                            && $this->viewName === null
                        ) {
                            $this->viewName = $arg->value->value;
                            continue;
                        }

                        if ($arg->name === null && $arg->value instanceof Array_) {
                            foreach ($arg->value->items as $item) {
                                if ($item instanceof ArrayItem
                                    && $item->key instanceof String_
                                    && in_array($item->key->value, $this->methods, true)
                                    && $item->value instanceof String_
                                    && $this->viewName === null
                                ) {
                                    $this->viewName = $item->value->value;
                                }
                            }
                        }
                    }
                }
            };

            $this->traverse($stmts, $visitor);
            $withVars = $visitor->withVars;
            $viewName = $visitor->viewName;

            if (empty($withVars) || $viewName === null) {
                continue;
            }

            $viewPath = $this->resolveViewPath($viewName);
            if (!$viewPath) {
                continue;
            }

            $viewContent = file_get_contents($viewPath);
            $subContents = $this->collectSubviewContents($viewContent);
            $combined = $viewContent . "\n" . implode("\n", $subContents);

            foreach ($withVars as $var) {
                if (!$this->variableUsedInView($combined, $var)) {
                    $locations[] = [
                        'file' => $file['path'],
                        'issue' => "Variable '\${$var}' passed to view but not used in '{$viewName}' or its included partials",
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
                message: 'All mailable variables are referenced in their templates.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: true,
            message: count($locations) . ' mailable variable(s) appear unused in the template.',
            locations: $locations,
            suggestion: 'Verify the variable is consumed by a view composer or an included partial.',
        );
    }

    private function resolveViewPath(string $view): ?string
    {
        $hints = config('view.paths', [resource_path('views')]);
        $name = str_replace('.', '/', $view);
        foreach ($hints as $path) {
            $candidate = $path . '/' . $name . '.blade.php';
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function collectSubviewContents(string $viewContent, int $depth = 0): array
    {
        if ($depth > 5) {
            return [];
        }
        $contents = [];
        $names = [];
        if (preg_match_all('/@include\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $viewContent, $m)) {
            $names = array_merge($names, $m[1]);
        }
        if (preg_match_all('/@component\s*\(\s*[\'"]([^\'"]+)[\'"]/', $viewContent, $m2)) {
            $names = array_merge($names, $m2[1]);
        }
        if (preg_match_all('/<x-([a-z0-9.\-]+)[\s>\/]/i', $viewContent, $m3)) {
            $names = array_merge($names, $m3[1]);
        }

        $hints = config('view.paths', [resource_path('views')]);
        foreach (array_unique($names) as $name) {
            $candidates = [
                str_replace('.', '/', $name) . '.blade.php',
                'components/' . str_replace('.', '/', $name) . '.blade.php',
            ];
            foreach ($candidates as $relPath) {
                $found = false;
                foreach ($hints as $hint) {
                    $candidate = $hint . '/' . $relPath;
                    if (file_exists($candidate)) {
                        $sub = file_get_contents($candidate);
                        $contents[] = $sub;
                        $contents = array_merge($contents, $this->collectSubviewContents($sub, $depth + 1));
                        $found = true;
                        break;
                    }
                }
                if ($found) {
                    break;
                }
            }
        }

        return $contents;
    }

    private function variableUsedInView(string $viewContent, string $var): bool
    {
        $q = preg_quote($var, '/');
        if (preg_match('/\$' . $q . '(?!\w)/', $viewContent)) {
            return true;
        }
        if (preg_match('/@(?:isset|empty)\s*\(\s*\$' . $q . '\b/', $viewContent)) {
            return true;
        }
        if (preg_match('/@(?:foreach|forelse)\s*\(\s*\$' . $q . '\b/', $viewContent)) {
            return true;
        }

        return false;
    }
}
