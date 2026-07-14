<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Env;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Env\AppKeyCheck;
use SajjadHossain\Doctor\Enums\Severity;

class AppKeyCheckTest extends TestCase
{
    /** @test */
    public function it_fails_when_app_key_is_empty(): void
    {
        config()->set('app.key', '');

        $result = (new AppKeyCheck())->run();

        $this->assertCheckFailed($result, Severity::Error);
        $this->assertStringContainsString('empty', strtolower($result->message));
    }

    /** @test */
    public function it_fails_when_app_key_is_not_base64_format(): void
    {
        config()->set('app.key', 'some-raw-key-without-prefix');

        $result = (new AppKeyCheck())->run();

        $this->assertCheckFailed($result, Severity::Error);
        $this->assertStringContainsString('base64', strtolower($result->message));
    }

    /** @test */
    public function it_passes_with_valid_app_key(): void
    {
        // base64: + 32 bytes = valid AES-256-CBC key
        config()->set('app.key', 'base64:' . base64_encode(str_repeat('x', 32)));
        config()->set('app.cipher', 'AES-256-CBC');

        $result = (new AppKeyCheck())->run();

        $this->assertCheckPassed($result);
    }
}
