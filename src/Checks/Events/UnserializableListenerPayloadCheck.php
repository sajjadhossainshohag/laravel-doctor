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
                if (preg_match('/ShouldQueue/', $content) && preg_match('/\bClosure\b|\bfn\s*\(/', $content)) {
                    $locations[] = [
                        'file' => $file->getRealPath(),
                        'issue' => 'Queued listener/event contains Closure which cannot be serialized',
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
                message: 'No unserializable payloads detected in queued events.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' queued event(s) may contain unserializable Closures.',
            locations: $locations,
            suggestion: 'Remove Closures from event properties or implement __serialize()/__unserialize().',
        );
    }
}
