<?php

namespace App\Services\Scoring;

final readonly class CriterionScore
{
    public function __construct(
        public string $criterion,
        public string $label,
        public int $points,
        public int $max,
        public string $explanation,
    ) {}
}
