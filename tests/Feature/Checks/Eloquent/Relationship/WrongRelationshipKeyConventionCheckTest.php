<?php

namespace SajjadHossain\Doctor\Tests\Feature\Checks\Eloquent\Relationship;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Eloquent\WrongRelationshipKeyConventionCheck;
use SajjadHossain\Doctor\Enums\Severity;

class WrongRelationshipKeyConventionCheckTest extends TestCase
{
    /**
     * Helper: invoke check against a path and return whether it failed
     * (any locations) — true means "check found at least one issue".
     */
    private function checkFails(string $path): bool
    {
        $check = (new WrongRelationshipKeyConventionCheck())
            ->withPaths([$path]);
        $result = $check->run();

        return ! $result->passed;
    }

    /** @test */
    public function it_passes_for_hasMany_with_conventional_fk_and_explicit_local_key(): void
    {
        // hasMany(Related::class, 'convention_fk', 'local_key') — FK is
        // the 2nd arg. Convention is met for the FK, the local key
        // (3rd arg) is irrelevant. With the OLD bug, the check would
        // have validated the local key 'local_pk_col' instead of
        // 'has_many_correct_parent_id'.
        $fails = $this->checkFails(
            __DIR__.'/../../../../Fixtures/App/Models/Isolated/HasManyCorrect'
        );
        $this->assertFalse($fails, 'hasMany with conventional FK + local key must NOT be flagged');
    }

    /** @test */
    public function it_flags_hasMany_with_non_conventional_fk(): void
    {
        // hasMany(Related::class, 'totally_unrelated_fk', 'local_pk') —
        // the FK (2nd arg) does NOT follow convention, so the check
        // SHOULD flag this. The OLD bug masked this because it checked
        // the local key ('local_pk') instead of the FK.
        $fails = $this->checkFails(
            __DIR__.'/../../../../Fixtures/App/Models/Isolated/HasManyWrong'
        );
        $this->assertTrue($fails, 'hasMany with non-conventional FK must be flagged');
    }

    /** @test */
    public function it_passes_for_hasOne_with_conventional_fk_and_explicit_local_key(): void
    {
        // hasOne with FK at arg index 1, local key at index 2.
        $fails = $this->checkFails(
            __DIR__.'/../../../../Fixtures/App/Models/Isolated/HasOneCorrect'
        );
        $this->assertFalse($fails, 'hasOne with conventional FK + local key must NOT be flagged');
    }

    /** @test */
    public function it_flags_hasOne_with_non_conventional_fk(): void
    {
        // hasOne(Related::class, 'unrelated_id', 'some_local_id') — the
        // FK (2nd arg) is non-conventional. The local key (3rd arg)
        // 'some_local_id' is NOT to be checked.
        $fails = $this->checkFails(
            __DIR__.'/../../../../Fixtures/App/Models/Isolated/HasOneWrong'
        );
        $this->assertTrue($fails, 'hasOne with non-conventional FK must be flagged');
    }

    /** @test */
    public function it_passes_for_belongsTo_with_conventional_fk(): void
    {
        $fails = $this->checkFails(
            __DIR__.'/../../../../Fixtures/App/Models/Isolated/BelongsToCorrect'
        );
        $this->assertFalse($fails, 'belongsTo with conventional FK must NOT be flagged');
    }

    /** @test */
    public function it_flags_belongsTo_with_non_conventional_fk(): void
    {
        // belongsTo FK is at arg index 1. Was already correct before
        // the fix; included here to guard against regression.
        $fails = $this->checkFails(
            __DIR__.'/../../../../Fixtures/App/Models/Isolated/BelongsToWrong'
        );
        $this->assertTrue($fails, 'belongsTo with non-conventional FK must be flagged');
    }
}