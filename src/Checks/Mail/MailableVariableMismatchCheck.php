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
        return Severity::Warning;
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

                // Collect variables passed via BOTH syntaxes:
                //  - legacy:  ->with('name', $value)
                //  - modern:  ->with(['name' => $value, ...])
                $withVars = [];
                if (preg_match_all('/->with\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,/', $content, $m1)) {
                    $withVars = array_merge($withVars, $m1[1]);
                }
                if (preg_match_all('/->with\s*\(\s*\[(.*?)\]\s*\)/s', $content, $m2)) {
                    foreach ($m2[1] as $block) {
                        if (preg_match_all('/[\'"]([a-zA-Z_][a-zA-Z0-9_]*)[\'"]\s*=>/', $block, $m3)) {
                            $withVars = array_merge($withVars, $m3[1]);
                        }
                    }
                }

                if (empty($withVars)) {
                    continue;
                }

                if (! preg_match('/->view\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $content, $viewM)) {
                    continue;
                }

                $viewName = $viewM[1];
                $viewPath = $this->resolveViewPath($viewName);
                if (! $viewPath) {
                    continue;
                }

                $viewContent = file_get_contents($viewPath);

                foreach ($withVars as $var) {
                    if (! $this->variableUsedInView($viewContent, $var)) {
                        $locations[] = [
                            'file' => $file->getRealPath(),
                            'issue' => "Variable '\${$var}' passed to view but not used in '{$viewName}'",
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
                message: 'All mailable variables match their templates.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' variable/template mismatch(es) detected.',
            locations: $locations,
            suggestion: 'Ensure all ->with() variables are referenced in the corresponding Blade template.',
        );
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
     * Detect whether $var is actually used inside the Blade view, considering:
     *   - direct:        {{ $var }}
     *   - array access:  {{ $var['key'] }} / @foreach($var as ...)
     *   - object access: {{ $var->prop }}
     *   - Blade @if/@isset/@empty
     */
    private function variableUsedInView(string $viewContent, string $var): bool
    {
        // Direct reference: $var followed by a non-word char (so we don't catch $variableLonger)
        if (preg_match('/\$' . preg_quote($var, '/') . '(?!\w)/', $viewContent)) {
            return true;
        }

        // {{ $var }} / {{{ $var }}}
        if (preg_match('/\{\{[^}]*\$' . preg_quote($var, '/') . '(?!\w)/', $viewContent)) {
            return true;
        }

        // @isset($var) / @empty($var)
        if (preg_match('/@(?:isset|empty)\s*\(\s*\$' . preg_quote($var, '/') . '\b/', $viewContent)) {
            return true;
        }

        // @foreach($var as ...) / @forelse($var as ...)
        if (preg_match('/@(?:foreach|forelse)\s*\(\s*\$' . preg_quote($var, '/') . '\b/', $viewContent)) {
            return true;
        }

        return false;
    }
}
