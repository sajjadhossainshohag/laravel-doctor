<?php

namespace SajjadHossain\Doctor\Checks\Mail;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class MailableMissingViewCheck implements HealthCheck
{
    private array $scanPaths = [];

    public function withPaths(array $paths): static
    {
        $this->scanPaths = $paths;
        return $this;
    }

    public function name(): string
    {
        return 'Mailable References Missing View';
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
        $paths = $this->scanPaths ?: [app_path('Mail')];

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

                $viewNames = [];

                // 1. ->view('name') — legacy and modern fluent API both
                //    use this to render a Blade view.
                if (preg_match_all('/->\s*view\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $stripped, $vm)) {
                    $viewNames = array_merge($viewNames, $vm[1]);
                }

                // 2. ->text('name') — legacy fluent form for plain-text view.
                if (preg_match_all('/->\s*text\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $stripped, $tm)) {
                    $viewNames = array_merge($viewNames, $tm[1]);
                }

                // 3. ->markdown('name') — legacy fluent form for markdown view.
                if (preg_match_all('/->\s*markdown\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $stripped, $mm)) {
                    $viewNames = array_merge($viewNames, $mm[1]);
                }

                // NOTE: ->html('...') is intentionally NOT treated as a view
                // reference. Mailable::html() takes a raw HTML string and
                // Laravel does NOT resolve it as a Blade view — see
                // Illuminate/Mail/Mailable::html(). Treating raw HTML as
                // a view name produced false positives like
                // ->html('<h1>Hello</h1>') flagging `<h1>Hello</h1>` as a
                // missing view.

                // 4. Modern Content-object API. Supports BOTH:
                //    a. Array-key form:  new Content(['view' => 'foo', ...])
                //       or:               new Content(['html' => 'foo', ...])
                //    b. Named-argument form: new Content(view: 'foo', ...)
                //                          new Content(html: 'foo', ...)
                //    The 'view', 'text', 'markdown' keys reference Blade
                //    views; the 'html' key takes a raw HTML string and
                //    must NOT be treated as a view.
                if (preg_match_all('/new\s+Content\s*\(/', $stripped)) {
                    foreach (['view', 'text', 'markdown'] as $k) {
                        // Array-key form: 'k' => 'value'
                        if (preg_match_all(
                            '/[\'"]'.preg_quote($k, '/').'[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/',
                            $stripped,
                            $cm
                        )) {
                            $viewNames = array_merge($viewNames, $cm[1]);
                        }
                        // Named-argument form: k: 'value'
                        if (preg_match_all(
                            '/\b'.preg_quote($k, '/').'\s*:\s*[\'"]([^\'"]+)[\'"]/',
                            $stripped,
                            $cmn
                        )) {
                            $viewNames = array_merge($viewNames, $cmn[1]);
                        }
                    }
                    // 'html' is documented to accept raw HTML — we do NOT
                    // collect its value as a view name even when it
                    // syntactically looks like one (e.g. `html: '<p>Hi</p>'`).
                }

                foreach (array_unique($viewNames) as $viewName) {
                    if (! view()->exists($viewName)) {
                        $locations[] = [
                            'file' => $file->getRealPath(),
                            'view' => $viewName,
                            'issue' => "Mailable references view '{$viewName}' which does not exist",
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
                message: 'All mailable views exist.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations).' mailable view reference(s) could not be resolved.',
            locations: $locations,
            suggestion: 'Create the missing view or fix the view reference in the mailable.',
        );
    }
}