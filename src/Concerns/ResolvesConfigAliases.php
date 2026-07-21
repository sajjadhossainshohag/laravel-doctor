<?php

namespace SajjadHossain\Doctor\Concerns;

trait ResolvesConfigAliases
{
    private function resolveConfigAliases(string $content): array
    {
        $targets = [
            'Illuminate\Support\Facades\Config',
        ];

        $aliases = ['Config'];

        if (preg_match_all('/^\s*use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/m', $content, $uses, PREG_SET_ORDER)) {
            foreach ($uses as $useMatch) {
                $fqcn = ltrim($useMatch[1], '\\');
                if (in_array($fqcn, $targets, true)) {
                    $alias = $useMatch[2] ?? substr($fqcn, strrpos($fqcn, '\\') + 1);
                    $aliases[] = $alias;
                    $aliases[] = $fqcn;
                }
            }
        }

        return array_unique($aliases);
    }
}
