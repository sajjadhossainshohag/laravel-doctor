<?php

namespace SajjadHossain\Doctor\Checks\Events;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class ListenerMissingHandleMethodCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Listener Missing handle() Method';
    }

    public function category(): string
    {
        return 'events';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = [app_path('Listeners')];

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

                // Try to resolve the FQCN for reflection. We look at the
                // first `class X` declaration in the file plus its
                // namespace + use imports, so a subclass that inherits
                // `handle()` from a base class is correctly detected.
                if (! preg_match_all('/^\s*(?:final\s+|abstract\s+)?(?:readonly\s+)?class\s+(\w+)/m', $content, $classMatches)) {
                    continue;
                }
                $namespaces = [];
                preg_match_all('/^\s*namespace\s+([\w\\\\]+)\s*;/m', $content, $nsMatches);
                foreach ($nsMatches[1] as $ns) {
                    $namespaces[] = $ns;
                }
                $uses = [];
                if (preg_match_all('/^\s*use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/m', $content, $useMatches)) {
                    foreach ($useMatches[1] as $i => $fqcn) {
                        $alias = $useMatches[2][$i] ?? null;
                        $short = $alias ?? basename(str_replace('\\', '/', $fqcn));
                        $uses[$short] = ltrim($fqcn, '\\');
                    }
                }

                foreach ($classMatches[1] as $shortClass) {
                    $fqcn = $uses[$shortClass] ?? null;
                    if ($fqcn === null) {
                        foreach ($namespaces as $ns) {
                            $candidate = $ns.'\\'.$shortClass;
                            if (class_exists($candidate)) {
                                $fqcn = $candidate;
                                break;
                            }
                        }
                    }
                    if ($fqcn === null || ! class_exists($fqcn)) {
                        continue;
                    }

                    try {
                        $reflection = new \ReflectionClass($fqcn);
                    } catch (\Throwable) {
                        continue;
                    }
                    if ($reflection->isAbstract() || $reflection->isTrait()) {
                        continue;
                    }

                    // A listener only needs ONE of Laravel's dispatch
                    // mechanisms — and any of them may be inherited from a
                    // base class or trait. Use reflection-based detection so
                    // `class Foo extends BaseFoo { }` (where BaseFoo has
                    // handle()) is correctly recognized.
                    $hasHandle = $reflection->hasMethod('handle');
                    $hasInvoke = $reflection->hasMethod('__invoke');
                    $hasSubscribe = $reflection->hasMethod('subscribe');

                    if ($hasHandle || $hasInvoke || $hasSubscribe) {
                        continue;
                    }

                    $locations[] = [
                        'file' => $file->getRealPath(),
                        'issue' => "Listener '{$shortClass}' has no handle(), __invoke(), or subscribe() method (checked via reflection, including inheritance)",
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
                message: 'All listener classes define a dispatch method.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' listener(s) missing a dispatch method.',
            locations: $locations,
            suggestion: 'Add a handle(), __invoke(), or subscribe() method to the listener class.',
        );
    }
}
