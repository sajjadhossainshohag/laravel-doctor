<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Console\Isolated;

use Illuminate\Support\Facades\Schedule;

/**
 * Cron with wildcard in MINUTE slot only — high-frequency. Wildcard in
 * HOUR slot (e.g. '0 * * * *' = hourly) is NOT frequent enough and
 * should not be flagged.
 */
class CronWildcardMinute
{
    public function schedule(): void
    {
        // Minute-wildcard (every minute) — frequent, must be flagged.
        Schedule::command('a:do-thing')->cron('* * * * *');

        // Hour-wildcard (hourly) — NOT flagged.
        Schedule::command('b:do-thing')->cron('0 * * * *');
    }
}