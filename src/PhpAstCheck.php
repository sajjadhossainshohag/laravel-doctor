<?php

namespace SajjadHossain\Doctor;

use PhpParser\Node;
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
    private static array $astCache = [];
    private static array $strippedContentCache = [];
    private static array $fileListCache = [];

    protected function parser(): Parser
    {
        return self::$sharedParser ??= (new ParserFactory)->createForHostVersion();
    }

    protected function stripComments(string $content): string
    {
        $key = md5($content);
        if (isset(self::$strippedContentCache[$key])) {
            return self::$strippedContentCache[$key];
        }

        $result = preg_replace('/\{\{--.*?--\}\}/s', '', $content);
        $result = preg_replace('#/\*.*?\*/#s', '', $result);
        $result = preg_replace('!//[^\n]*!', '', $result);

        if (count(self::$strippedContentCache) >= self::MAX_FILE_CACHE) {
            reset(self::$strippedContentCache);
            unset(self::$strippedContentCache[key(self::$strippedContentCache)]);
        }
        self::$strippedContentCache[$key] = $result;

        return $result;
    }

    protected function parse(string $code): ?array
    {
        $key = md5($code);
        if (isset(self::$astCache[$key])) {
            return self::$astCache[$key];
        }

        try {
            $ast = $this->parser()->parse($code);
            if (count(self::$astCache) >= self::MAX_FILE_CACHE) {
                reset(self::$astCache);
                unset(self::$astCache[key(self::$astCache)]);
            }
            self::$astCache[$key] = $ast;
            return $ast;
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
        $listCacheKey = md5(serialize($paths) . '|' . serialize($ignore));

        if (isset(self::$fileListCache[$listCacheKey])) {
            $files = self::$fileListCache[$listCacheKey];
        } else {
            $files = [];

            foreach ($paths as $path) {
                if (is_file($path)) {
                    if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
                        continue;
                    }

                    $realPath = realpath($path);

                    if ($realPath === false) {
                        continue;
                    }

                    if ($this->isIgnored($realPath, $ignore)) {
                        continue;
                    }

                    $files[] = ['path' => $realPath, 'mtime' => filemtime($realPath)];
                    continue;
                }

                if (!is_dir($path)) {
                    continue;
                }

                $dirFiles = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
                );

                foreach ($dirFiles as $file) {
                    if ($file->getExtension() !== 'php') {
                        continue;
                    }

                    $realPath = $file->getRealPath();

                    if ($this->isIgnored($realPath, $ignore)) {
                        continue;
                    }

                    $files[] = ['path' => $realPath, 'mtime' => $file->getMTime()];
                }
            }

            if (count(self::$fileListCache) >= self::MAX_FILE_CACHE) {
                reset(self::$fileListCache);
                unset(self::$fileListCache[key(self::$fileListCache)]);
            }
            self::$fileListCache[$listCacheKey] = $files;
        }

        foreach ($files as $file) {
            $cacheKey = $file['path'] . '|' . $file['mtime'];

            if (!isset(self::$fileContentCache[$cacheKey])) {
                if (count(self::$fileContentCache) >= self::MAX_FILE_CACHE) {
                    reset(self::$fileContentCache);
                    unset(self::$fileContentCache[key(self::$fileContentCache)]);
                }
                self::$fileContentCache[$cacheKey] = file_get_contents($file['path']);
            }

            yield ['path' => $file['path'], 'content' => self::$fileContentCache[$cacheKey]];
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

    protected function resolveFqcn(string $fileContent, string $shortName, ?array $stmts = null): ?string
    {
        $shortName = ltrim($shortName, '\\');

        if (class_exists($shortName) || interface_exists($shortName)) {
            return $shortName;
        }

        if ($stmts !== null) {
            return $this->resolveFqcnFromAst($stmts, $shortName);
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

    private function resolveFqcnFromAst(array $stmts, string $shortName): ?string
    {
        $namespace = null;
        $uses = [];

        $handleUse = function (Node\Stmt\UseUse $use, ?string $prefix = null) use (&$uses): void {
            $alias = $use->alias?->toString();
            $name = ltrim($use->name->toString(), '\\');
            if ($prefix !== null) {
                $name = $prefix . '\\' . $name;
            }
            $short = basename(str_replace('\\', '/', $name));
            if ($alias) {
                $uses[$alias] = $name;
            }
            $uses[$short] = $name;
        };

        foreach ($stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Use_) {
                foreach ($stmt->uses as $use) {
                    $handleUse($use);
                }
            } elseif ($stmt instanceof Node\Stmt\GroupUse) {
                $prefix = ltrim($stmt->prefix->toString(), '\\');
                foreach ($stmt->uses as $use) {
                    $handleUse($use, $prefix);
                }
            } elseif ($stmt instanceof Node\Stmt\Namespace_) {
                $namespace = $stmt->name?->toString();
                foreach ($stmt->stmts as $innerStmt) {
                    if ($innerStmt instanceof Node\Stmt\Use_) {
                        foreach ($innerStmt->uses as $use) {
                            $handleUse($use);
                        }
                    } elseif ($innerStmt instanceof Node\Stmt\GroupUse) {
                        $prefix = ltrim($innerStmt->prefix->toString(), '\\');
                        foreach ($innerStmt->uses as $use) {
                            $handleUse($use, $prefix);
                        }
                    }
                }
                break;
            }
        }

        if (isset($uses[$shortName])) {
            return $uses[$shortName];
        }

        if ($namespace !== null) {
            return $namespace . '\\' . $shortName;
        }

        return null;
    }
}
