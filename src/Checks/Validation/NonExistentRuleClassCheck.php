<?php

namespace SajjadHossain\Doctor\Checks\Validation;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class NonExistentRuleClassCheck implements HealthCheck
{
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
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = [app_path('Http/Requests'), app_path('Rules')];
        $declared = get_declared_classes();

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
                preg_match_all('/new\s+(\w+Rule\w*)\s*\(/', $content, $m);
                foreach ($m[1] as $ruleClass) {
                    $fqcn = $this->resolveFromUse($content, $ruleClass);
                    if ($fqcn && ! class_exists($fqcn)) {
                        $locations[] = [
                            'file' => $file->getRealPath(),
                            'issue' => "Custom Rule class '{$fqcn}' does not exist",
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
            message: count($locations).' custom rule class(es) not found.',
            locations: $locations,
            suggestion: 'Create the missing rule class or fix the import statement.',
        );
    }

    private function resolveFromUse(string $content, string $class): ?string
    {
        if (class_exists($class)) {
            return $class;
        }
        if (preg_match('/use\s+([\w\\\\]*'.preg_quote($class).')\s*;/', $content, $m)) {
            return $m[1];
        }
        if (preg_match('/namespace\s+([\w\\\\]+);/', $content, $m)) {
            $fqcn = $m[1].'\\'.$class;
            if (class_exists($fqcn)) {
                return $fqcn;
            }
        }

        return null;
    }
}
