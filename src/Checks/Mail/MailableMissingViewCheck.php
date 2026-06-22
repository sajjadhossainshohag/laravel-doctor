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

                // 1. ->view('name')
                $viewNames = [];
                if (preg_match_all('/->\s*view\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $stripped, $vm)) {
                    $viewNames = array_merge($viewNames, $vm[1]);
                }

                // 2. ->html('name')
                if (preg_match_all('/->\s*html\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $stripped, $hm)) {
                    $viewNames = array_merge($viewNames, $hm[1]);
                }

                // 3. ->text('name')
                if (preg_match_all('/->\s*text\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $stripped, $tm)) {
                    $viewNames = array_merge($viewNames, $tm[1]);
                }

                // 4. ->markdown('name')
                if (preg_match_all('/->\s*markdown\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $stripped, $mm)) {
                    $viewNames = array_merge($viewNames, $mm[1]);
                }

                // 5. Modern Content::view / Content::html / Content::text / Content::markdown
                // form: new Content(subject: ..., view: 'emails.foo')
                if (preg_match_all('/new\s+Content\s*\(/', $stripped)) {
                    foreach (['view', 'html', 'text', 'markdown'] as $k) {
                        if (preg_match_all('/[\'"]'.preg_quote($k, '/').'[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $stripped, $cm)) {
                            $viewNames = array_merge($viewNames, $cm[1]);
                        }
                    }
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
