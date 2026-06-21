<?php

namespace SajjadHossain\Doctor\Checks\Blade;

use Illuminate\Support\Facades\Route;
use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class MissingNamedRoutesCheck implements HealthCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Blade Route & URL Issues';
    }

    public function category(): string
    {
        return 'scan';
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $routes = Route::getRoutes();
        $locations = [];
        $scanned = 0;

        foreach ($this->scanPaths ?: config('view.paths', [resource_path('views')]) as $path) {
            if (!is_dir($path)) {
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
                $this->checkRouteCalls($routes, $content, $file->getRealPath(), $locations, $scanned);
                $this->checkUrlCalls($content, $file->getRealPath(), $locations, $scanned);
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: "All {$scanned} route() and url() references look correct.",
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' route/url reference(s) with issues.',
            locations: $locations,
            suggestion: 'Define missing named routes, or fix invalid names and URLs.',
        );
    }

    private function checkRouteCalls($routes, string $content, string $filePath, array &$locations, int &$scanned): void
    {
        preg_match_all(
            '/route\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            $content,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        foreach ($matches[1] as $match) {
            $scanned++;
            $name = $match[0];
            $offset = $match[1];
            $lineNo = substr_count(substr($content, 0, $offset), "\n") + 1;

            $routeExists = $routes->getByName($name) !== null;

            if (!str_starts_with($name, '__') && !$routeExists) {
                $locations[] = [
                    'file' => $filePath,
                    'line' => $lineNo,
                    'issue' => 'undefined named route',
                    'value' => $name,
                ];
                continue;
            }

            if (str_contains($name, '..') || str_starts_with($name, '.') || str_ends_with($name, '.')) {
                $locations[] = [
                    'file' => $filePath,
                    'line' => $lineNo,
                    'issue' => 'invalid route name format',
                    'value' => $name,
                ];
            }
        }
    }

    private function checkUrlCalls(string $content, string $filePath, array &$locations, int &$scanned): void
    {
        preg_match_all(
            '/url\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            $content,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        foreach ($matches[1] as $match) {
            $scanned++;
            $path = $match[0];
            $offset = $match[1];
            $lineNo = substr_count(substr($content, 0, $offset), "\n") + 1;

            if (preg_match('/[\$\.\+\->]/', $path)) {
                continue;
            }

            if (preg_match('/^[a-z_]+(\.[a-z_]+)+$/i', $path) && Route::getRoutes()->getByName($path) !== null) {
                $locations[] = [
                    'file' => $filePath,
                    'line' => $lineNo,
                    'issue' => 'url() should be route()',
                    'value' => $path,
                ];
                continue;
            }


        }
    }
}
