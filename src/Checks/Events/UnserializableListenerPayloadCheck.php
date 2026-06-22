<?php

namespace SajjadHossain\Doctor\Checks\Events;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class UnserializableListenerPayloadCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Unserializable Listener Payload';
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
        $paths = [app_path('Events'), app_path('Listeners')];

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

                // Find classes declared in this file.
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
                    // Resolve the FQCN of this class for reflection.
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

                    // Must actually implement ShouldQueue (via implements
                    // clause or interface inheritance — not just by
                    // string-matching 'ShouldQueue' in the file, which
                    // would match `use Illuminate\Contracts\Queue\ShouldQueue;`).
                    if (! $reflection->implementsInterface('Illuminate\\Contracts\\Queue\\ShouldQueue')) {
                        continue;
                    }

                    if ($this->hasUnserializableClosureProperty($reflection)) {
                        $locations[] = [
                            'file' => $file->getRealPath(),
                            'issue' => "Queued class '{$shortClass}' declares a Closure property — Closures cannot be serialized into a queue payload",
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
                message: 'No unserializable Closure properties in queued event/listener classes.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' queued event(s) may carry unserializable Closure properties.',
            locations: $locations,
            suggestion: 'Do not store Closures on queued event/listener properties; serialize primitives, scalars, or pass via the constructor as serializable values.',
        );
    }

    /**
     * Inspect a class's properties for any unserializable Closure — covers
     * typed properties (Closure, \Closure, ?Closure, union types, readonly,
     * static) AND untyped properties initialized to function/fn.
     */
    private function hasUnserializableClosureProperty(\ReflectionClass $reflection): bool
    {
        foreach ($reflection->getProperties() as $prop) {
            if ($prop->isStatic()) {
                // Static properties are NOT serialized into the queue
                // payload — skip them.
                continue;
            }

            $type = $prop->getType();
            if ($type !== null) {
                // Single named type
                if ($type instanceof \ReflectionNamedType) {
                    $name = $type->getName();
                    if ($this->isClosureType($name)) {
                        return true;
                    }
                }
                // Union/intersection types: iterate parts.
                if ($type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType) {
                    foreach ($type->getTypes() as $part) {
                        if ($part instanceof \ReflectionNamedType
                            && $this->isClosureType($part->getName())) {
                            return true;
                        }
                    }
                }
            }

            // Untyped property with a Closure initializer. We can't see
            // default values via reflection when the property is untyped,
            // so we fall back to scanning the file's source text.
        }

        // For untyped properties initialized to a Closure, scan the source
        // text (within the class body) for `public/protected/private $foo = function`
        // or `= fn (` patterns. This catches cases reflection can't see.
        try {
            $fileName = $reflection->getFileName();
            if (! is_string($fileName) || ! is_file($fileName)) {
                return false;
            }
            $contents = file_get_contents($fileName);
            $startLine = $reflection->getStartLine();
            $endLine = $reflection->getEndLine();
            if ($startLine <= 0 || $endLine <= 0) {
                return false;
            }
            $lines = explode("\n", $contents);
            $classBody = implode("\n", array_slice($lines, max(0, $startLine - 1), $endLine - $startLine + 1));
            if (preg_match('/(?:public|protected|private)\s+readonly\s+(?:[\w\\\\|&\[\]]+\s+)?\$\w+\s*=\s*(?:function\s*\(|fn\s*\()/', $classBody)) {
                return true;
            }
        } catch (\Throwable) {
            // ignore
        }

        return false;
    }

    private function isClosureType(string $name): bool
    {
        $name = ltrim($name, '?');
        // Match "Closure" or "\Closure" (fully-qualified).
        return $name === 'Closure' || str_ends_with($name, '\\Closure');
    }
}
