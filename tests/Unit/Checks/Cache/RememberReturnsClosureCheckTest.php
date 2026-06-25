<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Cache;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Cache\RememberReturnsClosureCheck;
use SajjadHossain\Doctor\Enums\Severity;

class RememberReturnsClosureCheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Use a serializing cache driver so the check actually scans.
        config(['cache.default' => 'file']);
    }

    /** @test */
    public function it_detects_closure_returned_inside_remember_callback(): void
    {
        $check = (new RememberReturnsClosureCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning, 'Closure');
    }

    /** @test */
    public function it_does_not_flag_remember_callback_using_url_in_string(): void
    {
        // Bug regression: the OLD naive `!//.*!` stripper corrupted
        // `'https://example.com'` to `'https:'`, which broke the
        // source parsing. Now using string-aware comment stripping.
        $check = (new RememberReturnsClosureCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Cache/Isolated']);

        $result = $check->run();

        // Only the ClosureReturned fixture must be flagged (it's a
        // real Closure return). HttpRemember and DoubleQuotedUrl must
        // NOT be flagged.
        $issues = array_column($result->locations, 'issue');
        foreach ($issues as $issue) {
            $this->assertStringNotContainsString(
                'https',
                $issue,
                'Flagged something containing a URL (likely parsing corruption): '.$issue
            );
        }
    }

    /** @test */
    public function it_still_flags_real_closure_return_alongside_url_fixtures(): void
    {
        // Ensure the URL-containing fixtures don't suppress detection
        // of the genuinely broken ClosureReturned fixture.
        $check = (new RememberReturnsClosureCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Cache/Isolated']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning);
        // Issue must mention Closure / Cache::remember closure pattern.
        $this->assertGreaterThanOrEqual(1, count($result->locations));
        $issues = array_column($result->locations, 'issue');
        $found = false;
        foreach ($issues as $issue) {
            if (str_contains($issue, 'Closure')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected at least one Closure-returning remember() flagged');
    }
}