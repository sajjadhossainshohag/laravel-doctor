<?php

namespace SajjadHossain\Doctor\Tests\Feature\Checks\Validation;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Validation\NonExistentRuleClassCheck;
use SajjadHossain\Doctor\Enums\Severity;

class NonExistentRuleClassCheckTest extends TestCase
{
    /** @test */
    public function it_detects_non_existent_rule_class_in_requests(): void
    {
        $result = (new NonExistentRuleClassCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Http/Requests'])
            ->run();

        $this->assertCheckFailed($result, Severity::Warning);
    }
}
