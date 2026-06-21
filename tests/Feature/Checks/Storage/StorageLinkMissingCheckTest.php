<?php

namespace SajjadHossain\Doctor\Tests\Feature\Checks\Storage;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Storage\MissingStorageSymlinkCheck;
use SajjadHossain\Doctor\Enums\Severity;

class StorageLinkMissingCheckTest extends TestCase
{
    /** @test */
    public function it_detects_missing_public_storage_symlink(): void
    {
        $result = (new MissingStorageSymlinkCheck())->run();

        $this->assertCheckFailed($result, Severity::Warning, 'symlink');
    }
}
