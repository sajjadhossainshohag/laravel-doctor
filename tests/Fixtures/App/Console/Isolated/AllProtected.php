<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Console\Isolated;

use Illuminate\Support\Facades\Schedule;

/**
 * All frequent tasks in this file are protected. Must NOT be flagged.
 */
class AllProtected
{
    public function schedule(): void
    {
        Schedule::command('a:do-thing')->everyMinute()->withoutOverlapping();
        Schedule::command('b:do-thing')->everyMinute()->onOneServer();
        Schedule::command('c:do-thing')->cron('*/5 * * * *')->withoutOverlapping();
    }
}