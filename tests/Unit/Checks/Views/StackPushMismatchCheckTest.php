<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Views;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Views\StackPushMismatchCheck;
use SajjadHossain\Doctor\Enums\Severity;

class StackPushMismatchCheckTest extends TestCase
{
    /** @test */
    public function it_detects_push_without_matching_stack(): void
    {
        // Create a temporary view with @push but no @stack
        $dir = sys_get_temp_dir().'/stack_test_'.uniqid();
        mkdir($dir, 0777, true);

        $content = <<<'BLADE'
@push('scripts')
    <script>console.log('hi');</script>
@endpush
<p>Hello</p>
BLADE;
        file_put_contents($dir.'/test.blade.php', $content);

        config()->set('view.paths', [$dir]);

        $result = (new StackPushMismatchCheck())->run();

        // Cleanup
        unlink($dir.'/test.blade.php');
        rmdir($dir);

        $this->assertTrue($result->passed); // Info level, always passes
        $this->assertNotEmpty($result->locations);
    }

    /** @test */
    public function it_passes_when_push_and_stack_match(): void
    {
        $dir = sys_get_temp_dir().'/stack_test2_'.uniqid();
        mkdir($dir, 0777, true);

        $content = <<<'BLADE'
@push('scripts')
    <script>console.log('hi');</script>
@endpush
@stack('scripts')
BLADE;
        file_put_contents($dir.'/test.blade.php', $content);

        config()->set('view.paths', [$dir]);

        $result = (new StackPushMismatchCheck())->run();

        unlink($dir.'/test.blade.php');
        rmdir($dir);

        $this->assertTrue($result->passed);
        $this->assertEmpty($result->locations);
    }
}
