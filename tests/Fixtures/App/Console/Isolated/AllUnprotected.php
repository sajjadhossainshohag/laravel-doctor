<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Console\Isolated;

use Illuminate\Support\Facades\Schedule;

/**
 * All frequent tasks in this file are unprotected. All MUST be flagged.
 */
class AllUnprotected
{
    public function schedule(): void
    {
        Schedule::command('a:do-thing')->everyMinute();
        Schedule::command('b:do-thing')->cron('* * * * *');
        Schedule::command('c:do-thing')->everyFiveMinutes();
    }
}