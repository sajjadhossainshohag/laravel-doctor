<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Console;

use Illuminate\Console\Scheduling\Schedule;

class BrokenArtisanSchedule
{
    public function schedule(Schedule $schedule): void
    {
        $schedule->command('deleted:command')->daily();
        $schedule->command('another:gone')->weekly();
    }
}
