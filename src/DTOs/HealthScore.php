<?php

namespace SajjadHossain\Doctor\DTOs;

final class HealthScore
{
    public const WEIGHTS = [
        'schema'     => 25,
        'runtime'    => 25,
        'routes'     => 20,
        'views'      => 15,
        'components' => 10,
        'jobs'       => 5,
    ];

    public function __construct(
        public readonly array $categoryScores,
        public readonly float $overall,
    ) {}

    public static function calculate(array $reports): self
    {
        $categoryScores = [];
        $totalWeight = 0;
        $weightedSum = 0;

        foreach ($reports as $report) {
            $cat = $report->category;
            $score = $report->score();
            $categoryScores[$cat] = $score;
            $weight = self::WEIGHTS[$cat] ?? 10;
            $totalWeight += $weight;
            $weightedSum += $score * $weight;
        }

        $overall = $totalWeight > 0 ? round($weightedSum / $totalWeight, 1) : 100.0;

        return new self($categoryScores, $overall);
    }
}
