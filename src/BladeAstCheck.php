<?php

namespace SajjadHossain\Doctor;

abstract class BladeAstCheck extends PhpAstCheck
{
    private static array $compiledBladeCache = [];

    protected function compileBlade(string $rawBladeContent): string
    {
        $key = md5($rawBladeContent);
        if (isset(self::$compiledBladeCache[$key])) {
            return self::$compiledBladeCache[$key];
        }

        try {
            $compiled = app('blade.compiler')->compileString($rawBladeContent);
            self::$compiledBladeCache[$key] = $compiled;
            return $compiled;
        } catch (\Throwable) {
            return '<?php // blade compilation failed';
        }
    }

    protected function parseBlade(string $rawContent): ?array
    {
        $compiled = $this->compileBlade($rawContent);

        return $this->parse($compiled);
    }

    protected function mapDirectiveLines(string $rawContent, string $directive): array
    {
        $lines = [];
        preg_match_all(
            '/@' . preg_quote($directive, '/') . '\s*\(/',
            $rawContent,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        foreach ($matches[0] as [$token, $offset]) {
            $lines[] = substr_count(substr($rawContent, 0, $offset), "\n") + 1;
        }

        return $lines;
    }

    protected function viewPaths(): array
    {
        return config('view.paths', [resource_path('views')]);
    }

    protected function scanViewFiles(): iterable
    {
        return $this->scanPhpFiles($this->viewPaths());
    }
}
