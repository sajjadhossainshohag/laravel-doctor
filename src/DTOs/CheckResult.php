<?php

namespace SajjadHossain\Doctor\DTOs;

use SajjadHossain\Doctor\Enums\Severity;

final class CheckResult
{
    public function __construct(
        public readonly string $check,
        public readonly string $category,
        public readonly Severity $severity,
        public readonly bool $passed,
        public readonly string $message,
        public readonly array $locations = [],
        public readonly ?string $suggestion = null,
    ) {}
}
