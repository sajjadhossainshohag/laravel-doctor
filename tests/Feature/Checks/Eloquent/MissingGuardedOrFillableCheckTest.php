<?php

namespace SajjadHossain\Doctor\Tests\Feature\Checks\Eloquent;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Eloquent\MissingGuardedOrFillableCheck;
use SajjadHossain\Doctor\Enums\Severity;

class MissingGuardedOrFillableCheckTest extends TestCase
{
    /** @test */
    public function it_detects_model_with_neither_fillable_nor_guarded(): void
    {
        $check = (new MissingGuardedOrFillableCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Models/Broken']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Error);
        $this->assertNotEmpty($result->locations);
        $this->assertStringContainsString('NoFillableModel', $result->locations[0]['issue'] ?? '');
    }

    /** @test */
    public function it_passes_for_model_with_fillable_defined(): void
    {
        $check = (new MissingGuardedOrFillableCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Models/Good']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_passes_for_model_with_empty_guarded_array(): void
    {
        // $guarded = [] is an explicit "I know what I'm doing" declaration
        // and must not be flagged.
        $check = (new MissingGuardedOrFillableCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Models/Isolated/EmptyGuarded']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_recognizes_public_visibility_modifier(): void
    {
        // `public $fillable = [...]` (not just `protected`) must be
        // recognized as a valid declaration.
        $check = (new MissingGuardedOrFillableCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Models/Isolated/PublicFillable']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_recognizes_typed_fillable_property(): void
    {
        // `public array $fillable = [...]` (typed property) must be
        // recognized.
        $check = (new MissingGuardedOrFillableCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Models/Isolated/TypedFillable']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_recognizes_string_form_of_guarded(): void
    {
        // `$guarded = '*'` (Laravel's legacy single-string form) must
        // be recognized as a valid declaration.
        $check = (new MissingGuardedOrFillableCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Models/Isolated/StringGuarded']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_recognizes_inherited_fillable_from_parent_class(): void
    {
        // A child model that does NOT declare $fillable / $guarded
        // itself, but whose parent class does, must be considered safe.
        $check = (new MissingGuardedOrFillableCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Models/Isolated/InheritedFillable']);

        $result = $check->run();

        $this->assertCheckPassed($result);
    }

    /** @test */
    public function it_flags_broken_model_with_no_protection(): void
    {
        // NoGuardNoFillModel is a clear broken case — should be flagged.
        $check = (new MissingGuardedOrFillableCheck())
            ->withPaths([__DIR__.'/../../../Fixtures/App/Models/Isolated/NoGuardNoFill']);

        $result = $check->run();

        $this->assertCheckFailed($result, Severity::Error);
        $this->assertStringContainsString('NoGuardNoFillModel', $result->locations[0]['issue'] ?? '');
    }
}