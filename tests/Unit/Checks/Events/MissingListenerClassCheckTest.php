<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Events;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Events\MissingListenerClassCheck;
use SajjadHossain\Doctor\Enums\Severity;

class MissingListenerClassCheckTest extends TestCase
{
    /** @test */
    public function it_detects_non_existent_listener_classes(): void
    {
        $check = (new MissingListenerClassCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Providers']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $locations = $result->locations;
        $this->assertNotEmpty($locations);
        $this->assertStringContainsString('DeletedListener', $locations[0]['issue'] ?? '');
    }
}
