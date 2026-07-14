<?php

namespace SajjadHossain\Doctor\Checks\Validation;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class NonExistentRuleClassCheck implements HealthCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Non-Existent Custom Rule Class';
    }

    public function category(): string
    {
        return 'validation';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = $this->scanPaths ?: [app_path('Http/Requests'), app_path('Rules')];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $content = file_get_contents($file->getRealPath());
                $stripped = preg_replace('#/\*.*?\*/#s', '', $content);
                $stripped = preg_replace('!//[^\n]*!', '', $stripped);

                // Match `new ClassName(...)` where ClassName is any
                // valid PHP identifier — including a leading-backslash
                // fully-qualified name (`new \App\Rules\Foo()`). The
                // leading `\` is critical for FQCN references.
                if (preg_match_all('/new\s+\\\\?([A-Z][\w\\\\]*)\s*\(/', $stripped, $m)) {
                    foreach ($m[1] as $ruleClass) {
                        // Skip Laravel built-in Rule static factory methods.
                        $short = basename(str_replace('\\', '/', $ruleClass));
                        if (in_array($short, ['Rule', 'Date', 'File', 'Fluent'], true)) {
                            continue;
                        }

                        $resolved = $this->resolveClassName($stripped, $ruleClass);

                        if ($resolved !== null && class_exists($resolved)) {
                            continue;
                        }
                        if (class_exists($ruleClass)) {
                            continue;
                        }
                        if (class_exists(ltrim($ruleClass, '\\'))) {
                            continue;
                        }

                        $locations[] = [
                            'file' => $file->getRealPath(),
                            'issue' => "Class '{$ruleClass}' referenced in `new ...(...)` does not exist",
                        ];
                    }
                }
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'All custom rule classes exist.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' rule class(es) not found.',
            locations: $locations,
            suggestion: 'Create the missing rule class, fix the import statement, or check the namespace.',
        );
    }

    /**
     * Resolve a possibly-short class name against `use` imports in the file
     * and the file's namespace. Returns null if no resolution is possible.
     */
    private function resolveClassName(string $content, string $class): ?string
    {
        $class = ltrim($class, '\\');
        if (class_exists($class)) {
            return $class;
        }
        if (preg_match_all('/^\s*use\s+([\w\\\\]+)(?:\s+as\s+\w+)?\s*;/m', $content, $uses)) {
            foreach ($uses[1] as $fqcn) {
                $parts = explode('\\', ltrim($fqcn, '\\'));
                if (end($parts) === $class) {
                    return ltrim($fqcn, '\\');
                }
            }
        }
        if (preg_match('/^\s*namespace\s+([\w\\\\]+);/m', $content, $ns)) {
            return $ns[1] . '\\' . $class;
        }
        return null;
    }
}