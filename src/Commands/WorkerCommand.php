<?php

namespace SajjadHossain\Doctor\Commands;

use Illuminate\Console\Command;
use SajjadHossain\Doctor\CheckRegistry;

class WorkerCommand extends Command
{
    protected $signature = 'doctor:worker
        {--only= : Comma-separated categories to run}
        {--output= : Path to write serialized results}';

    protected $description = '[Internal] Run a subset of doctor checks for parallel execution';

    public function handle(CheckRegistry $registry): int
    {
        $checkClasses = $registry->all();
        $only = $this->option('only');
        $outputPath = $this->option('output');

        $instances = [];
        foreach ($checkClasses as $class) {
            $instances[] = new $class();
        }

        if ($only) {
            $categories = explode(',', $only);
            $instances = array_values(array_filter($instances, fn ($c) => in_array($c->category(), $categories, true)));
        }

        $results = [];
        foreach ($instances as $check) {
            $results[] = $check->run();
        }

        if ($outputPath) {
            file_put_contents($outputPath, serialize($results));
        }

        return 0;
    }
}
