<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Config;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Config\NonExistentConfigFileCheck;
use SajjadHossain\Doctor\Enums\Severity;

class NonExistentConfigFileCheckTest extends TestCase
{
    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturePath = __DIR__.'/../../../Fixtures/App/Isolated/NonExistentConfigFile';
    }

    /** @test */
    public function it_detects_config_call_to_non_existent_file(): void
    {
        $check = (new NonExistentConfigFileCheck())
            ->withPaths([$this->fixturePath . '/with_bad_ref.php']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertCount(2, $result->locations);
    }

    /** @test */
    public function it_passes_when_config_file_exists(): void
    {
        $check = (new NonExistentConfigFileCheck())
            ->withPaths([$this->fixturePath . '/with_good_ref.php']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_detects_config_get_call_to_non_existent_file(): void
    {
        $check = (new NonExistentConfigFileCheck())
            ->withPaths([$this->fixturePath . '/with_config_get_only.php']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertCount(1, $result->locations);
        $this->assertStringContainsString('payment.php', $result->locations[0]['issue']);
    }

    /** @test */
    public function it_skips_namespace_prefixed_config_keys(): void
    {
        $check = (new NonExistentConfigFileCheck())
            ->withPaths([$this->fixturePath . '/with_namespace_key.php']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_skips_when_config_is_provided_by_package_at_runtime(): void
    {
        config(['package_test' => ['key' => 'value']]);

        $check = (new NonExistentConfigFileCheck())
            ->withPaths([$this->fixturePath . '/with_package_config.php']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_skips_when_default_value_is_provided(): void
    {
        $check = (new NonExistentConfigFileCheck())
            ->withPaths([$this->fixturePath . '/with_default_value.php']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_passes_when_no_config_calls_are_present(): void
    {
        $check = (new NonExistentConfigFileCheck())
            ->withPaths([$this->fixturePath . '/clean.php']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }
}
