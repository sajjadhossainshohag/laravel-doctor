<?php

namespace SajjadHossain\Doctor;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use SajjadHossain\Doctor\Contracts\HealthCheck;

abstract class PhpAstCheck implements HealthCheck
{
    protected const MAX_FILE_CACHE = 1000;

    private static ?Parser $sharedParser = null;
    private static array $fileContentCache = [];

    protected function parser(): Parser
    {
        return self::$sharedParser ??= (new ParserFactory)->createForHostVersion();
    }

    protected function stripComments(string $content): string
    {
        $content = preg_replace('/\{\{--.*?--\}\}/s', '', $content);
        $content = preg_replace('#/\*.*?\*/#s', '', $content);
        $content = preg_replace('!//[^\n]*!', '', $content);

        return $content;
    }

    protected function parse(string $code): ?array
    {
        try {
            return $this->parser()->parse($code);
        } catch (\PhpParser\Error) {
            return null;
        }
    }

    protected function traverse(array $stmts, NodeVisitorAbstract ...$visitors): void
    {
        $traverser = new NodeTraverser;
        foreach ($visitors as $visitor) {
            $traverser->addVisitor($visitor);
        }
        $traverser->traverse($stmts);
    }

    protected function scanPhpFiles(array $paths): iterable
    {
        $ignore = config("doctor.ignore.{$this->category()}", []);

        foreach ($paths as $path) {
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

                $realPath = $file->getRealPath();

                if ($this->isIgnored($realPath, $ignore)) {
                    continue;
                }

                $mtime = $file->getMTime();
                $cacheKey = $realPath . '|' . $mtime;

                if (!isset(self::$fileContentCache[$cacheKey])) {
                    if (count(self::$fileContentCache) >= self::MAX_FILE_CACHE) {
                        reset(self::$fileContentCache);
                        $firstKey = key(self::$fileContentCache);
                        unset(self::$fileContentCache[$firstKey]);
                    }
                    self::$fileContentCache[$cacheKey] = file_get_contents($realPath);
                }

                yield ['path' => $realPath, 'content' => self::$fileContentCache[$cacheKey]];
            }
        }
    }

    protected function isIgnored(string $path, array $patterns): bool
    {
        $normalized = str_replace('\\', '/', $path);
        foreach ($patterns as $pattern) {
            $normalizedPattern = str_replace('\\', '/', $pattern);
            if (fnmatch($normalizedPattern, $normalized) || str_contains($normalized, $normalizedPattern)) {
                return true;
            }
        }
        return false;
    }

    protected function resolveFqcn(string $fileContent, string $shortName): ?string
    {
        $shortName = ltrim($shortName, '\\');

        if (class_exists($shortName) || interface_exists($shortName)) {
            return $shortName;
        }

        if (preg_match_all('/^\s*use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/m', $fileContent, $uses, PREG_SET_ORDER)) {
            foreach ($uses as $useMatch) {
                $fqcn = ltrim($useMatch[1], '\\');
                $alias = $useMatch[2] ?? null;
                $shortFromFqcn = basename(str_replace('\\', '/', $fqcn));
                $shortNames = $alias ? [$alias, $shortFromFqcn] : [$shortFromFqcn];
                if (in_array($shortName, $shortNames, true)) {
                    return $fqcn;
                }
            }
        }

        if (preg_match('/^\s*namespace\s+([\w\\\\]+);/m', $fileContent, $ns)) {
            return $ns[1] . '\\' . $shortName;
        }

        return null;
    }
}
