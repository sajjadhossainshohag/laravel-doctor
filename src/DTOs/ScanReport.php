<?php

namespace SajjadHossain\Doctor\DTOs;

final class ScanReport
{
    public function __construct(
        public readonly string $category,
        public readonly array $results,
        public readonly float $duration,
    ) {}

    public function passed(): int
    {
        return count(array_filter($this->results, fn (CheckResult $r) => $r->passed));
    }

    public function total(): int
    {
        return count($this->results);
    }

    public function score(): float
    {
        return $this->total() > 0
            ? round(($this->passed() / $this->total()) * 100, 1)
            : 100.0;
    }
}
