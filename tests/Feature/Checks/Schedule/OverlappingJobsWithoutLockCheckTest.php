<?php

namespace SajjadHossain\Doctor\Tests\Feature\Checks\Schedule;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Schedule\OverlappingJobsWithoutLockCheck;
use SajjadHossain\Doctor\Enums\Severity;

class OverlappingJobsWithoutLockCheckTest extends TestCase
{
    /**
     * @return array<int, array{file?: string, line?: int, issue?: string}>
     */
    private function locationsFor(array $paths): array
    {
        $check = (new OverlappingJobsWithoutLockCheck())
            ->withPaths($paths);

        return $check->run()->locations;
    }

    /** @test */
    public function it_only_flags_unprotected_chain_in_mixed_protected_unprotected_file(): void
    {
        // Bug regression: OLD file-level detection incorrectly
        // considered ALL frequent tasks protected if ANY task in
        // the file had ->withoutOverlapping(). The NEW per-chain
        // detection must flag only the unprotected chain.
        $locations = $this->locationsFor([
            __DIR__.'/../../../Fixtures/App/Console/Isolated/MixedTasks.php',
        ]);

        $this->assertNotEmpty($locations, 'Expected at least one flag for MixedTasks file');
        $issues = array_column($locations, 'issue');
        $bFound = false;
        $aFound = false;
        foreach ($issues as $issue) {
            if (str_contains($issue, "'b:do-thing'")) {
                $bFound = true;
            }
            if (str_contains($issue, "'a:do-thing'")) {
                $aFound = true;
            }
        }
        $this->assertTrue($bFound, 'Expected to flag unprotected task b:do-thing');
        $this->assertFalse($aFound, 'Expected NOT to flag protected task a:do-thing');
    }

    /** @test */
    public function it_does_not_flag_when_all_frequent_tasks_are_protected(): void
    {
        $locations = $this->locationsFor([
            __DIR__.'/../../../Fixtures/App/Console/Isolated/AllProtected.php',
        ]);

        $this->assertEmpty($locations, 'All tasks protected — expected no flags, got: '.json_encode($locations));
    }

    /** @test */
    public function it_flags_all_unprotected_frequent_tasks(): void
    {
        $locations = $this->locationsFor([
            __DIR__.'/../../../Fixtures/App/Console/Isolated/AllUnprotected.php',
        ]);

        // Three unprotected tasks — should produce 3 flags.
        $this->assertCount(3, $locations, 'Expected 3 flags for 3 unprotected tasks');
    }

    /** @test */
    public function it_only_flags_minute_wildcard_cron_not_hour_wildcard(): void
    {
        $locations = $this->locationsFor([
            __DIR__.'/../../../Fixtures/App/Console/Isolated/CronWildcardMinute.php',
        ]);

        $issues = array_column($locations, 'issue');
        // a:do-thing cron '* * * * *' (every minute) — flagged.
        $minuteFlag = false;
        foreach ($issues as $issue) {
            if (str_contains($issue, "'a:do-thing'")) {
                $minuteFlag = true;
            }
        }
        // b:do-thing cron '0 * * * *' (hourly) — NOT flagged.
        $hourFlag = false;
        foreach ($issues as $issue) {
            if (str_contains($issue, "'b:do-thing'")) {
                $hourFlag = true;
            }
        }
        $this->assertTrue($minuteFlag, 'Expected minute-wildcard cron to be flagged');
        $this->assertFalse($hourFlag, 'Hour-wildcard cron should NOT be flagged');
    }
}