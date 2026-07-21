<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Security;

use SajjadHossain\Doctor\Checks\Security\RequestAllInCreateCheck;
use SajjadHossain\Doctor\Enums\Severity;
use SajjadHossain\Doctor\Tests\TestCase;

class RequestAllInCreateCheckTest extends TestCase
{
    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturePath = __DIR__ . '/../../../Fixtures/App/Isolated/RequestAllInCreate';
    }

    /** @test */
    public function it_detects_request_all_in_create(): void
    {
        $check = (new RequestAllInCreateCheck())
            ->withPaths([$this->fixturePath]);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Error);
        $this->assertStringContainsString('mass-assignment', $result->message);
        $this->assertStringContainsString('RequestAllInCreateSmell.php', $result->locations[0]['file']);
    }

    /** @test */
    public function it_detects_eleven_occurrences_in_smell_file(): void
    {
        $check = (new RequestAllInCreateCheck())
            ->withPaths([$this->fixturePath]);

        $result = $check->run();

        $this->assertCount(11, $result->locations);
    }

    /** @test */
    public function it_does_not_inflate_locations_from_clean_file(): void
    {
        $check = (new RequestAllInCreateCheck())
            ->withPaths([$this->fixturePath]);

        $result = $check->run();

        $this->assertCount(11, $result->locations);

        foreach ($result->locations as $loc) {
            $this->assertStringContainsString('RequestAllInCreateSmell.php', $loc['file']);
        }
    }

    /** @test */
    public function it_passes_when_only_clean_file_is_scanned(): void
    {
        $cleanPath = $this->fixturePath . '/RequestAllInCreateClean.php';

        $check = (new RequestAllInCreateCheck())
            ->withPaths([$cleanPath]);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_passes_when_no_files_found(): void
    {
        $check = (new RequestAllInCreateCheck())
            ->withPaths([__DIR__ . '/../../../Fixtures/empty-dir']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_does_not_flag_non_request_static_calls(): void
    {
        $check = (new RequestAllInCreateCheck())
            ->withPaths([$this->fixturePath . '/NonRequestStaticCall.php']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_detects_all_request_helper_variants(): void
    {
        $check = (new RequestAllInCreateCheck())
            ->withPaths([__DIR__ . '/../../../Fixtures/App/Isolated/RequestHelper']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Error);
        $this->assertCount(4, $result->locations);
    }
}
