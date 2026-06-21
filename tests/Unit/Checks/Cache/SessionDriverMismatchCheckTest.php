<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Cache;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Cache\SessionDriverMismatchCheck;
use SajjadHossain\Doctor\Enums\Severity;
use Illuminate\Support\Facades\Schema;

class SessionDriverMismatchCheckTest extends TestCase
{
    /** @test */
    public function it_detects_database_session_driver_when_table_missing(): void
    {
        config(['session.driver' => 'database']);

        $result = (new SessionDriverMismatchCheck())->run();

        $this->assertCheckFailed($result, Severity::Warning);
    }

    /** @test */
    public function it_passes_when_database_session_table_exists(): void
    {
        config(['session.driver' => 'database']);

        Schema::create('sessions', function ($table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        $result = (new SessionDriverMismatchCheck())->run();

        $this->assertCheckPassed($result);

        Schema::dropIfExists('sessions');
    }
}
