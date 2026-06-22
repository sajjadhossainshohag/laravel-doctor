<?php

namespace SajjadHossain\Doctor\Checks\Livewire;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class MissingLivewireActionMethodCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Livewire Action Method Missing';
    }

    public function category(): string
    {
        return 'livewire';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $componentPaths = [app_path('Livewire')];

        // For each Livewire component, find its OWN view (not the parent
        // view that uses <livewire:foo>) and inspect wire:click targets
        // against the component's methods. Previously, the check mapped
        // parent Blade views to Livewire components via <livewire:foo>
        // which is the consumer side, not the component's own template.
        $components = $this->indexComponents($componentPaths);
        $methodNamesByComponent = [];
        foreach ($components as $shortName => $className) {
            $methodNamesByComponent[$shortName] = $this->collectMethodNames($className);
        }

        foreach ($components as $shortName => $className) {
            $componentView = $this->resolveComponentView($className, $shortName);
            if ($componentView === null || ! is_file($componentView)) {
                continue;
            }

            $content = file_get_contents($componentView);

            // Determine the scope of this view. The OWNING component is
            // the one mapped to this view; any <livewire:child> embedded
            // inside the view delegates wire:click handlers to `child`,
            // not to us. We collect embedded child component names so we
            // can skip their wire:click handlers.
            $embeddedChildren = [];
            if (preg_match_all('/<livewire:([\w-]+)(?=[\s>\/])/', $content, $livewireMatches)) {
                foreach ($livewireMatches[1] as $childKebab) {
                    $studly = str_replace(' ', '', ucwords(str_replace('-', ' ', $childKebab)));
                    $embeddedChildren[$childKebab] = $studly;
                }
            }
            // Also handle @livewire('foo', ...) directive.
            if (preg_match_all('/@livewire\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $livewireDirMatches)) {
                foreach ($livewireDirMatches[1] as $childKebab) {
                    $studly = str_replace(' ', '', ucwords(str_replace('-', ' ', $childKebab)));
                    $embeddedChildren[$childKebab] = $studly;
                }
            }

            // Strip embedded child component view content so their
            // wire:click targets don't get attributed to the parent.
            // (We can't easily resolve nested views, so we only do a
            // coarse block-strip: anything between <livewire:CHILD ...>
            // and the matching closing tag — heuristic but better than
            // nothing for the common case.)
            $scopedContent = $this->stripEmbeddedChildBlocks($content);

            // Match wire:click="action" (or 'action'). Allow method
            // names with letters, digits, underscores, and dots for
            // nested-component dispatch ('$wire.parent.method').
            preg_match_all('/wire:click(?:\.[a-z]+)?\s*=\s*[\'"]([\w.$]+)[\'"]/', $scopedContent, $m);
            $componentMethods = $methodNamesByComponent[$shortName] ?? [];

            foreach ($m[1] as $action) {
                // $dispatch('foo', ...) or $wire.parent.method — skip
                // (those are dispatch to a named event / nested component).
                if ($action === '' || str_starts_with($action, '$')) {
                    continue;
                }
                // Bare method name only.
                $method = explode('.', $action)[0];
                if ($method === '' || $method === '$wire') {
                    continue;
                }
                if (! in_array($method, $componentMethods, true)) {
                    $locations[] = [
                        'file' => $componentView,
                        'component' => $className,
                        'issue' => "wire:click=\"{$action}\" — method '{$method}' not found on Livewire component '{$shortName}'",
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
                message: 'All wire:click actions have corresponding methods.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' wire:click action(s) with no matching method.',
            locations: $locations,
            suggestion: 'Add the action method to the corresponding Livewire component.',
        );
    }

    /**
     * Build map: short name (StudlyCase of filename) => FQCN, by scanning component paths.
     *
     * @return array<string, string>
     */
    private function indexComponents(array $paths): array
    {
        $index = [];

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

                if (preg_match('/^namespace\s+([\w\\\\]+);/m', $content, $ns)
                    && preg_match('/class\s+(\w+)/', $content, $cm)) {
                    $class = ltrim($ns[1] . '\\' . $cm[1], '\\');
                    $index[class_basename($class)] = $class;
                }
            }
        }

        return $index;
    }

    /**
     * Resolve the Blade view file associated with a Livewire component class.
     * Checks the `view` property first, then falls back to the conventional
     * livewire/<kebab-name>.blade.php location.
     */
    private function resolveComponentView(string $className, string $shortName): ?string
    {
        try {
            $reflection = new \ReflectionClass($className);
            if ($reflection->hasProperty('view')) {
                $prop = $reflection->getProperty('view');
                $prop->setAccessible(true);
                $defaults = $reflection->getDefaultProperties();
                $view = $defaults['view'] ?? null;
                if (is_string($view) && $view !== '') {
                    $hints = config('view.paths', [resource_path('views')]);
                    foreach ($hints as $hint) {
                        $candidate = $hint.'/'.str_replace('.', '/', $view).'.blade.php';
                        if (file_exists($candidate)) {
                            return $candidate;
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // ignore reflection errors
        }

        // Fallback: conventional location: livewire/<kebab>.blade.php
        $kebab = strtolower(preg_replace('/(?<!^)([A-Z])/', '-$1', $shortName));
        $conventional = resource_path('views/livewire/'.$kebab.'.blade.php');
        if (file_exists($conventional)) {
            return $conventional;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function collectMethodNames(string $className): array
    {
        $methods = [];
        if (! class_exists($className)) {
            return $methods;
        }
        try {
            $reflection = new \ReflectionClass($className);
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isStatic() || $method->isAbstract()) {
                    continue;
                }
                if (in_array($method->getName(), ['__construct', 'mount', 'render', 'boot', 'booted', 'initializeTraits'], true)) {
                    continue;
                }
                if ($method->getDeclaringClass()->getName() === 'Livewire\Component') {
                    continue;
                }
                $methods[] = $method->getName();
            }
        } catch (\Throwable) {
            // ignore
        }

        return $methods;
    }

    /**
     * Replace any <livewire:CHILD ...>...</livewire:CHILD> (or self-closing)
     * block in the view content with blank space, so wire:click handlers
     * inside embedded children aren't attributed to the parent component.
     */
    private function stripEmbeddedChildBlocks(string $content): string
    {
        // Self-closing form: <livewire:foo ... />
        $content = preg_replace(
            '/<livewire:[\w-]+(?:[^>"\']|"[^"]*"|\'[^\']*\')*\/>/s',
            '',
            $content
        );
        // Paired form: <livewire:foo ...>...</livewire:foo>. We do a
        // simple non-nested strip — nested <livewire:> inside another
        // child's slot is uncommon and the heuristic catches the
        // majority of cases.
        $content = preg_replace(
            '/<livewire:[\w-]+(?:[^>"\']|"[^"]*"|\'[^\']*\')*>.*?<\/livewire:[\w-]+>/s',
            '',
            $content
        );

        return $content;
    }
}
