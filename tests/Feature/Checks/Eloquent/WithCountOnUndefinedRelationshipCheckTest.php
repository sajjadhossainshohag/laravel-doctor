<?php

namespace SajjadHossain\Doctor\Tests\Feature\Checks\Eloquent;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Eloquent\WithCountOnUndefinedRelationshipCheck;
use SajjadHossain\Doctor\Enums\Severity;

class WithCountOnUndefinedRelationshipCheckTest extends TestCase
{
    /**
     * Helper: invoke check against a path and return locations.
     *
     * @return array<int, array{file: string, issue: string}>
     */
    private function locationsFor(string $path): array
    {
        $check = (new WithCountOnUndefinedRelationshipCheck())
            ->withPaths([$path]);

        return $check->run()->locations;
    }

    /** @test */
    public function it_does_not_flag_nested_dot_notation_with_defined_base_relation(): void
    {
        // 'posts.comments' — base 'posts' is a real relationship on
        // WithCountNestedUser. The OLD code would have looked for a
        // method literally named 'posts.comments' and incorrectly
        // flagged this.
        $locations = $this->locationsFor(
            __DIR__.'/../../../Fixtures/App/Models/Isolated/WithCountNested'
        );

        $this->assertEmpty($locations, 'Expected no issues but got: '.json_encode($locations));
    }

    /** @test */
    public function it_does_not_flag_nested_dot_notation_when_used_in_model_with_chain(): void
    {
        // Place a small controller-style file in the same directory as
        // the model so the heuristic can match the model class.
        $dir = sys_get_temp_dir().'/with_count_test_'.uniqid();
        mkdir($dir, 0777, true);

        $modelCode = <<<'PHP'
<?php
namespace TestNS;

use Illuminate\Database\Eloquent\Model;

class TestParent extends Model
{
    protected $table = 'test_parents';
    protected $fillable = ['name'];

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TestChild::class);
    }
}

class TestChild extends Model
{
    protected $table = 'test_children';
    protected $fillable = ['name'];
}
PHP;

        $usageCode = <<<'PHP'
<?php
namespace TestNS;

class UsageClass
{
    public function index()
    {
        // Single-arg nested form (chained on TestParent::query)
        TestParent::query()->withCount('children.grandchildren')->get();

        // Array-form nested
        TestParent::query()->withCount(['children.grandchildren'])->get();

        return null;
    }
}
PHP;

        file_put_contents($dir.'/Models.php', $modelCode);
        file_put_contents($dir.'/Usage.php', $usageCode);

        $locations = $this->locationsFor($dir);

        // Cleanup
        unlink($dir.'/Models.php');
        unlink($dir.'/Usage.php');
        rmdir($dir);

        $this->assertEmpty(
            $locations,
            'Expected no false positives on nested withCount, got: '.json_encode($locations)
        );
    }
}