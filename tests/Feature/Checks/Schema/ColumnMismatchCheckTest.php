<?php

namespace SajjadHossain\Doctor\Tests\Feature\Checks\Schema;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Schema\ColumnMismatchCheck;
use SajjadHossain\Doctor\Enums\Severity;
use Illuminate\Support\Facades\Schema;

class ColumnMismatchCheckTest extends TestCase
{
    /** @test */
    public function it_detects_missing_table(): void
    {
        require_once __DIR__.'/../../../Fixtures/App/Models/Schema/Profile.php';

        Schema::dropIfExists('profiles');

        $result = (new ColumnMismatchCheck())
            ->withModels([\SajjadHossain\Doctor\Tests\Fixtures\App\Models\Schema\Profile::class])
            ->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertStringContainsString('Table does not exist', $result->message);
        $this->assertStringContainsString('Profile', $result->message);
        $this->assertStringContainsString('Create the missing database table', $result->suggestion);
    }

    /** @test */
    public function it_detects_column_mismatches(): void
    {
        Schema::dropIfExists('profiles');
        Schema::create('profiles', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email');
        });

        $result = (new ColumnMismatchCheck())
            ->withModels([\SajjadHossain\Doctor\Tests\Fixtures\App\Models\Schema\Profile::class])
            ->run();

        $this->assertCheckFailed($result, Severity::Warning);
        $this->assertStringContainsString('profiles', $result->message);
        $this->assertStringContainsString('bio', $result->message);
        $this->assertStringContainsString('missing column', $result->suggestion);

        Schema::dropIfExists('profiles');
    }

    /** @test */
    public function it_passes_when_table_and_columns_match(): void
    {
        Schema::dropIfExists('profiles');
        Schema::create('profiles', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->text('bio');
        });

        $result = (new ColumnMismatchCheck())
            ->withModels([\SajjadHossain\Doctor\Tests\Fixtures\App\Models\Schema\Profile::class])
            ->run();

        $this->assertCheckPassed($result);

        Schema::dropIfExists('profiles');
    }
}
