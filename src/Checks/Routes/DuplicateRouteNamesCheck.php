<?php

namespace SajjadHossain\Doctor\Checks\Routes;

use Illuminate\Support\Facades\Route;
use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class DuplicateRouteNamesCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Duplicate Route Names';
    }

    public function category(): string
    {
        return 'routes';
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $routes = Route::getRoutes();

        // Group every named route by its name so we capture ALL routes
        // sharing a name, not just the ones after the first.
        $byName = [];

        foreach ($routes as $route) {
            $name = $route->getName();

            if ($name === null) {
                continue;
            }

            $byName[$name][] = [
                'name' => $name,
                'uri' => $route->uri(),
                'method' => implode('|', $route->methods()),
            ];
        }

        $duplicateGroups = array_filter($byName, fn (array $entries) => count($entries) > 1);

        if (empty($duplicateGroups)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No duplicate route names found.',
            );
        }

        // Split into "trailing-dot" groups (a ->name('prefix.') group whose
        // child routes never called ->name(), so they all collapse onto the
        // literal prefix) vs genuine accidental duplicates.
        $trailingDotGroups = [];
        $genuineDuplicates = [];

        foreach ($duplicateGroups as $name => $entries) {
            if (str_ends_with($name, '.')) {
                $trailingDotGroups[$name] = $entries;
            } else {
                $genuineDuplicates[$name] = $entries;
            }
        }

        $locations = [];
        $messageParts = [];

        foreach ($trailingDotGroups as $name => $entries) {
            $count = count($entries);
            $messageParts[] = "Group name prefix \"{$name}\" has {$count} routes with no per-route name — did you forget ->name() inside the group?";

            foreach ($entries as $entry) {
                $locations[] = $entry + ['issue' => 'missing_child_name'];
            }
        }

        foreach ($genuineDuplicates as $name => $entries) {
            $count = count($entries);
            $messageParts[] = "Route name \"{$name}\" is reused by {$count} routes.";

            foreach ($entries as $entry) {
                $locations[] = $entry + ['issue' => 'duplicate_name'];
            }
        }

        $suggestionParts = [];

        if (! empty($trailingDotGroups)) {
            $suggestionParts[] = 'For groups with a trailing-dot name prefix (e.g. ->name(\'merchant.\')), '
                . 'add an explicit ->name(\'x\') to every route inside the group so it becomes '
                . '\'merchant.x\' instead of colliding on the literal prefix.';
        }

        if (! empty($genuineDuplicates)) {
            $suggestionParts[] = 'For reused route names, rename one of each colliding pair so names are unique.';
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: implode(' ', $messageParts),
            locations: $locations,
            suggestion: implode(' ', $suggestionParts),
        );
    }
}
