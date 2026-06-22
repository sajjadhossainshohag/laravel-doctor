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
                $stripped = preg_replace('#/\*.*?\*/#s', '', $content);
                $stripped = preg_replace('!//[^\n]*!', '', $stripped);

                if (! preg_match('/ShouldQueue/', $stripped)) {
                    continue;
                }

                // Only flag a class with a public property typed as Closure
                // or assigned a Closure. Local closures inside methods are
                // not part of the serialized queue payload.
                $hasUnserializableProperty = false;

                // public Closure $foo;  (typed property)
                if (preg_match_all('/(public|protected|private)\s+(\?)?\s*Closure\s+\$\w+/', $stripped)) {
                    $hasUnserializableProperty = true;
                }

                // public/private/protected $foo = function ... or = fn ...
                if (preg_match_all('/(public|protected|private)\s+\$\w+\s*=\s*(?:function\s*\(|fn\s*\()/', $stripped)) {
                    $hasUnserializableProperty = true;
                }

                if ($hasUnserializableProperty) {
                    $locations[] = [
                        'file' => $file->getRealPath(),
                        'issue' => 'Queued listener/event declares a Closure property — Closures cannot be serialized into a queue payload',
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
}
