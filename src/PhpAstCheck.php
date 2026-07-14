<?php

namespace SajjadHossain\Doctor;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use SajjadHossain\Doctor\Contracts\HealthCheck;

abstract class PhpAstCheck implements HealthCheck
{
    private ?Parser $parser = null;

    protected function parser(): Parser
    {
        return $this->parser ??= (new ParserFactory)->createForHostVersion();
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
                $content = file_get_contents($realPath);

                yield ['path' => $realPath, 'content' => $content];
            }
        }
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
