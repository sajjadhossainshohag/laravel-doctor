<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Config;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Config\NonExistentConfigKeyCheck;
use SajjadHossain\Doctor\Enums\Severity;

class NonExistentConfigKeyCheckTest extends TestCase
{
    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturePath = __DIR__.'/../../../Fixtures/App/Isolated/NonExistentConfigKey';
    }

    /** @test */
    public function it_detects_config_call_with_invalid_key(): void
    {
        config(['mail' => ['default' => 'smtp']]);

        $check = (new NonExistentConfigKeyCheck())
            ->withPaths([$this->fixturePath . '/with_invalid_key.php']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertStringContainsString('mail.invalid_key', $result->locations[0]['issue']);
    }

    /** @test */
    public function it_detects_config_get_call_with_invalid_key(): void
    {
        config(['mail' => ['default' => 'smtp']]);

        $check = (new NonExistentConfigKeyCheck())
            ->withPaths([$this->fixturePath . '/with_config_get_invalid.php']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertStringContainsString('mail.invalid_key', $result->locations[0]['issue']);
    }

    /** @test */
    public function it_passes_when_config_key_exists(): void
    {
        config(['mail' => ['default' => 'smtp']]);

        $check = (new NonExistentConfigKeyCheck())
            ->withPaths([$this->fixturePath . '/with_valid_key.php']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_skips_namespace_prefixed_config_keys(): void
    {
        $check = (new NonExistentConfigKeyCheck())
            ->withPaths([$this->fixturePath . '/with_namespace_key.php']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_passes_with_nested_config_key(): void
    {
        config(['services' => ['stripe' => ['key' => 'sk_test']]]);

        $check = (new NonExistentConfigKeyCheck())
            ->withPaths([$this->fixturePath . '/with_nested_valid_key.php']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_skips_when_default_value_is_provided(): void
    {
        $check = (new NonExistentConfigKeyCheck())
            ->withPaths([$this->fixturePath . '/with_default_value.php']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_skips_when_config_file_does_not_exist(): void
    {
        $check = (new NonExistentConfigKeyCheck())
            ->withPaths([$this->fixturePath . '/../NonExistentConfigFile/with_bad_ref.php']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }
}
