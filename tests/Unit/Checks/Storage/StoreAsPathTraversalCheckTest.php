<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Storage;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Storage\StoreAsPathTraversalCheck;
use SajjadHossain\Doctor\Enums\Severity;

class StoreAsPathTraversalCheckTest extends TestCase
{
    /** @test */
    public function it_detects_path_traversal_in_storeas(): void
    {
        config()->set('doctor.scan_paths', [__DIR__.'/../../../Fixtures/App/Storage']);

        $result = (new StoreAsPathTraversalCheck())->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertCount(3, $result->locations);
    }
}
