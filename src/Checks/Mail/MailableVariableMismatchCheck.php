<?php

namespace SajjadHossain\Doctor\Checks\Mail;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class MailableVariableMismatchCheck implements HealthCheck
{
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
        $paths = [app_path('Mail')];

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

                $withVars = [];
                // ->with('name', $value) — single string key
                if (preg_match_all('/->\s*with\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,/', $stripped, $m1)) {
                    $withVars = array_merge($withVars, $m1[1]);
                }
                // ->with(['name' => $value, ...])
                if (preg_match_all('/->\s*with\s*\(\s*\[(.*?)\]\s*\)/s', $stripped, $m2)) {
                    foreach ($m2[1] as $block) {
                        if (preg_match_all('/[\'"]([a-zA-Z_][a-zA-Z0-9_]*)[\'"]\s*=>/', $block, $m3)) {
                            $withVars = array_merge($withVars, $m3[1]);
                        }
                    }
                }

                if (empty($withVars)) {
                    continue;
                }

                // Find any view reference in the mailable. Supports BOTH the legacy
                // ->view('foo') API and the modern Content-object API
                // (new Content(view: 'foo')).
                $viewName = $this->firstViewName($stripped);
                if ($viewName === null) {
                    continue;
                }

                $viewPath = $this->resolveViewPath($viewName);
                if (! $viewPath) {
                    continue;
                }

                $viewContent = file_get_contents($viewPath);

                // Resolve all @include, @component, and <x-foo /> subviews
                // from the main view and union their content so a
                // variable used inside a partial is still considered used.
                $subviewContents = $this->collectSubviewContents($viewContent);
                $combined = $viewContent."\n".implode("\n", $subviewContents);

                foreach ($withVars as $var) {
                    if (! $this->variableUsedInView($combined, $var)) {
                        $locations[] = [
                            'file' => $file->getRealPath(),
                            'issue' => "Variable '\${$var}' passed to view but not used in '{$viewName}' or its included partials",
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
                message: 'All mailable variables are referenced in their templates.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: true,
            message: count($locations).' mailable variable(s) appear unused in the template (and any included partials). This is informational — variables may be consumed by view composers or third-party listeners.',
            locations: $locations,
        );
    }

    private function firstViewName(string $content): ?string
    {
        foreach (['view', 'html', 'text', 'markdown'] as $method) {
            if (preg_match('/->\s*'.preg_quote($method, '/').'\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $content, $m)) {
                return $m[1];
            }
        }
        // Modern API: new Content(view: 'foo', ...) or new Content(markdown: 'foo', ...).
        // Look for `new Content(` and pick the first recognized key.
        if (preg_match('/new\s+Content\s*\(/', $content)) {
            foreach (['view', 'html', 'text', 'markdown'] as $k) {
                if (preg_match('/[\'"]'.preg_quote($k, '/').'[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $content, $cm)) {
                    return $cm[1];
                }
            }
        }
        return null;
    }

    private function resolveViewPath(string $view): ?string
    {
        $hints = config('view.paths', [resource_path('views')]);
        $name = str_replace('.', '/', $view);
        foreach ($hints as $path) {
            $candidate = $path.'/'.$name.'.blade.php';
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Recursively collect the contents of any @include('...') /
     * @component('...') / <x-foo /> partials referenced from this view,
     * so a variable used inside a partial is still considered "used".
     *
     * @return list<string>
     */
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
        // Anonymous components: <x-foo /> or <x-foo>...</x-foo>
        if (preg_match_all('/<x-([a-z0-9.\-]+)[\s>\/]/i', $viewContent, $m3)) {
            $names = array_merge($names, $m3[1]);
        }

        $hints = config('view.paths', [resource_path('views')]);
        foreach (array_unique($names) as $name) {
            $candidates = [
                str_replace('.', '/', $name).'.blade.php',
                'components/'.str_replace('.', '/', $name).'.blade.php',
            ];
            foreach ($candidates as $relPath) {
                $found = false;
                foreach ($hints as $hint) {
                    $candidate = $hint.'/'.$relPath;
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

    /**
     * Detect whether $var is actually used inside the Blade view, considering:
     *   - direct:        {{ $var }}
     *   - array access:  {{ $var['key'] }} / @foreach($var as ...)
     *   - object access: {{ $var->prop }}
     *   - Blade @if/@isset/@empty
     */
    private function variableUsedInView(string $viewContent, string $var): bool
    {
        if (preg_match('/\$' . preg_quote($var, '/') . '(?!\w)/', $viewContent)) {
            return true;
        }
        if (preg_match('/\{\{[^}]*\$' . preg_quote($var, '/') . '(?!\w)/', $viewContent)) {
            return true;
        }
        if (preg_match('/@(?:isset|empty)\s*\(\s*\$' . preg_quote($var, '/') . '\b/', $viewContent)) {
            return true;
        }
        if (preg_match('/@(?:foreach|forelse)\s*\(\s*\$' . preg_quote($var, '/') . '\b/', $viewContent)) {
            return true;
        }

        return false;
    }
}
