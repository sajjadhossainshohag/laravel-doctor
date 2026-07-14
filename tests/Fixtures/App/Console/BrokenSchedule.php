<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Console;

class BrokenSchedule
{
    public function schedule($schedule): void
    {
        $schedule->command(\SajjadHossain\Doctor\Tests\Fixtures\App\Console\NonExistentCommand::class)->daily();
    }
}
