<?php

namespace SajjadHossain\Doctor\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use SajjadHossain\Doctor\DoctorServiceProvider;
use SajjadHossain\Doctor\DTOs\CheckResult;
use SajjadHossain\Doctor\Enums\Severity;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [DoctorServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function assertCheckFailed(
        CheckResult $result,
        Severity $severity,
        ?string $messageContains = null,
    ): void {
        $this->assertFalse($result->passed, 'Expected check to fail but it passed.');
        $this->assertEquals($severity, $result->severity);

        if ($messageContains) {
            $this->assertStringContainsString($messageContains, $result->message);
        }
    }

    protected function assertCheckPassed(
        CheckResult $result,
    ): void {
        $this->assertTrue($result->passed, "Expected check to pass but it failed: {$result->message}");
    }
}
