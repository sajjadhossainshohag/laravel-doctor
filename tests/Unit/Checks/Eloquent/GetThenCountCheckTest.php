<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Eloquent;

use SajjadHossain\Doctor\Checks\Eloquent\GetThenCountCheck;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\Tests\TestCase;

class GetThenCountCheckTest extends TestCase
{
    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturePath = __DIR__.'/../../../Fixtures/App/Isolated/GetThenCount';
    }

    /** @test */
    public function it_detects_get_then_count(): void
    {
        $check = (new GetThenCountCheck())
            ->withPaths([$this->fixturePath]);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertStringContainsString('get()/->all()', $result->message);
        $this->assertStringContainsString('GetThenCountSmell.php', $result->locations[0]['file']);
    }

    /** @test */
    public function it_detects_three_occurrences_in_smell_file(): void
    {
        $check = (new GetThenCountCheck())
            ->withPaths([$this->fixturePath]);

        $result = $check->run();

        $this->assertCount(3, $result->locations);
    }

    /** @test */
    public function it_does_not_inflate_locations_from_clean_file(): void
    {
        $check = (new GetThenCountCheck())
            ->withPaths([$this->fixturePath]);

        $result = $check->run();

        $this->assertCount(3, $result->locations);

        foreach ($result->locations as $loc) {
            $this->assertStringContainsString('GetThenCountSmell.php', $loc['file']);
        }
    }

    /** @test */
    public function it_passes_when_only_clean_file_is_scanned(): void
    {
        $cleanPath = $this->fixturePath.'/GetThenCountClean.php';

        $check = (new GetThenCountCheck())
            ->withPaths([$cleanPath]);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_passes_when_no_files_found(): void
    {
        $check = (new GetThenCountCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/empty-dir']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }
}
