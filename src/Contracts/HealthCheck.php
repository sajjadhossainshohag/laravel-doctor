<?php

namespace SajjadHossain\Doctor\Contracts;

use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

interface HealthCheck
{
    public function name(): string;

    public function category(): string;

    public function run(): CheckResult;

    public function severity(): Severity;
}
