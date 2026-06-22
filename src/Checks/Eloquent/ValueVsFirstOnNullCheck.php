<?php

namespace SajjadHossain\Doctor\Checks\Eloquent;

use SajjadHossain\Doctor\Contracts\HealthCheck;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

class ValueVsFirstOnNullCheck implements HealthCheck
{
    public function name(): string
    {
        return '->first()->column on Null Result';
    }

    public function category(): string
    {
        return 'eloquent';
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function run(): CheckResult
    {
        $locations = [];
        $paths = config('doctor.scan_paths', [app_path(), resource_path('views')]);

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
                if (! preg_match('/->first\s*\(\)\s*->\w+/', $content)) {
                    continue;
                }

                if ($this->allCallsAreGuarded($content)) {
                    continue;
                }

                $locations[] = [
                    'file' => $file->getRealPath(),
                    'issue' => '->first()->property called without null check',
                ];
            }
        }

        if (empty($locations)) {
            return new CheckResult(
                check: $this->name(),
                category: $this->category(),
                severity: $this->severity(),
                passed: true,
                message: 'No unsafe ->first()->property chains detected.',
            );
        }

        return new CheckResult(
            check: $this->name(),
            category: $this->category(),
            severity: $this->severity(),
            passed: false,
            message: count($locations) . ' unsafe ->first()->property chain(s) detected.',
            locations: $locations,
            suggestion: 'Use ->value(\'column\') or optional($result) to avoid null dereference.',
        );
    }

    private function allCallsAreGuarded(string $content): bool
    {
        preg_match_all('/->first\s*\(\)\s*->\w+/', $content, $matches, PREG_OFFSET_CAPTURE);

        if (empty($matches[0])) {
            return true;
        }

        foreach ($matches[0] as [$match, $offset]) {
            if (! $this->callIsGuarded($content, $offset)) {
                return false;
            }
        }

        return true;
    }

    private function callIsGuarded(string $content, int $offset): bool
    {
        // Look at the 400 chars BEFORE the ->first() call. This is a much
        // tighter guard than the old logic, which used a coarse "any open
        // @if" that suppressed unrelated blocks.
        $contextStart = max(0, $offset - 400);
        $context = substr($content, $contextStart, $offset - $contextStart);

        // Inside groupBy/partition closure — each group is guaranteed ≥1 item.
        if (
            preg_match('/\b(groupBy|partition)\s*\(/', $context)
            && preg_match('/\b(map|each|filter|transform)\s*\(\s*function\s*\(/', $context)
        ) {
            return true;
        }

        // Blade @if / @elseif / @unless with a count/isNotEmpty/comparison
        // guard that closes BEFORE the ->first() call. Also handle split
        // statements (@if (...) @else body @endif): treat @else as a
        // half-close so a guard on the IF branch still applies if the
        // ->first() call is in the IF body (before @else) or in the ELSE
        // body (where the inverse applies). We track which branch we're in
        // by counting @if/@else/@endif.
        if ($this->bladeGuardApplies($context)) {
            return true;
        }

        // PHP `if (...count/isNotEmpty/!==null) {` opening that hasn't been
        // closed before the ->first() call.
        $openIf = preg_match_all('/\bif\s*\(/', $context);
        $closeBrace = preg_match_all('/\}\s*/', $context);
        if ($openIf > $closeBrace && preg_match('/if\s*\([^)]*(?:->count\s*\(\s*\)\s*[!><]|->isNotEmpty\s*\(\s*\)|!?==?\s*null)\s*\)/s', $context)) {
            return true;
        }

        // Calls chained with nullsafe ?-> after first().
        $afterFirst = substr($content, $offset + strlen('->first()'), 15);
        if (str_contains($afterFirst, '?->')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether a Blade @if/@elseif/@unless guard applies at this
     * point in the context. Handles:
     *   - @if (count) ... @endif  (closed before the call)
     *   - @if (count) ... @else ... @endif  (split form — we accept either
     *     branch as long as the IF itself was guarded by count/isNotEmpty)
     *   - @unless (empty) ... @endunless
     */
    private function bladeGuardApplies(string $context): bool
    {
        // Normalize: count @if/@elseif/@unless opens and @endif/@endunless
        // closes in order. As soon as we see an @if/@unless with a guard
        // and a matching @endif/@endunless BEFORE the call, we're safe.
        $tokens = [];
        if (preg_match_all('/@(if|elseif|else|unless|endif|endunless)\b/', $context, $m)) {
            foreach ($m[1] as $tag) {
                $tokens[] = strtolower($tag);
            }
        }
        // Walk tokens: find an opening @if or @unless that has a guard
        // expression and a matching close. If the call is INSIDE that
        // guarded block (i.e., hasn't been closed yet at this point),
        // we're safe.
        $depth = 0;
        $guardedDepth = -1; // depth at which a guard is in effect
        $i = 0;
        $len = count($tokens);
        while ($i < $len) {
            $t = $tokens[$i];
            if ($t === 'if' || $t === 'unless') {
                $depth++;
                if ($guardedDepth === -1) {
                    // Look at the expression following this @if/@unless.
                    // We need the source text after this token's word up
                    // to the next @ token. Simpler: pull the text after
                    // the @if/@unless word and check for the guard
                    // pattern.
                    $pos = 0;
                    $search = 0;
                    for ($k = 0; $k <= $depth - 1; $k++) {
                        // find position of the n-th occurrence
                    }
                    // simpler: regex against the whole context after this
                    // @if/@unless
                    if (preg_match('/@'.$t.'\b[^@]*?(?:->count\s*\(\s*\)\s*[!><]|->isNotEmpty\s*\(\s*\)|[!><]=?\s*0\b|!==\s*null|!=\s*null)/s', $context, $gm, PREG_OFFSET_CAPTURE)) {
                        $guardOffset = $gm[0][1];
                        // Only count this if the guard expression is
                        // BEFORE the @if/@unless we've just seen
                        // — which it must be since we extracted from the
                        // same string and the match starts at @if/@unless.
                        if ($guardOffset >= 0) {
                            $guardedDepth = $depth;
                        }
                    }
                }
            } elseif ($t === 'else') {
                // @else does not close the block; it switches branches.
                // Guard still applies on the IF side (regardless of which
                // branch we're in).
            } elseif ($t === 'endif' || $t === 'endunless') {
                if ($depth === $guardedDepth) {
                    $guardedDepth = -1; // Guard closed before this point.
                }
                $depth--;
            }
            $i++;
        }

        return $guardedDepth !== -1;
    }
}
