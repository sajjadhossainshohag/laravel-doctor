<?php

namespace SajjadHossain\Doctor\Tests\Feature\Commands;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Facades\Doctor;

class DoctorScanCommandTest extends TestCase
{
    /** @test */
    public function it_exits_zero_when_no_issues_found(): void
    {
        $this->artisan('doctor:scan', [
            '--only' => 'env',
        ])->assertExitCode(0);
    }

    /** @test */
    public function it_outputs_valid_json_when_json_flag_used(): void
    {
        $this->artisan('doctor:scan --json')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_runs_only_specified_categories(): void
    {
        $this->artisan('doctor:scan', [
            '--only' => 'routes,views',
        ])->assertExitCode(0);
    }
}
