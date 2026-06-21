<?php

namespace SajjadHossain\Doctor\Commands;

use Illuminate\Console\Command;
use SajjadHossain\Doctor\ScanResultCache;

class CacheClearCommand extends Command
{
    protected $signature = 'doctor:cache:clear';

    protected $description = 'Clear cached scan results';

    public function handle(ScanResultCache $cache): int
    {
        $cache->flush();
        $this->info('Doctor scan cache cleared.');

        return 0;
    }
}
