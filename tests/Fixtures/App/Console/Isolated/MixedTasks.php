<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Console\Isolated;

use Illuminate\Support\Facades\Schedule;

/**
 * Mixed tasks in a single file — task A has withoutOverlapping(), task B
 * does NOT. The OLD file-level detection incorrectly considered both
 * protected. The NEW per-chain detection must flag only task B.
 */
class MixedTasks
{
    public function schedule(): void
    {
        // Task A — protected, must NOT be flagged.
        Schedule::command('a:do-thing')
            ->everyMinute()
            ->withoutOverlapping();

        // Task B — unprotected, MUST be flagged.
        Schedule::command('b:do-thing')
            ->everyMinute();

        // Task C — different cadence (hourly), must NOT be flagged even
        // though it's unprotected.
        Schedule::command('c:do-thing')
            ->hourly();
    }
}