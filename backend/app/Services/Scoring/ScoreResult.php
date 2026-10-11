<?php

namespace App\Services\Scoring;

use App\Enums\Category;

final readonly class ScoreResult
{
    /**
     * @param  list<CriterionScore>  $details
     * @param  list<string>  $flags
     */
    public function __construct(
        public int $total,
        public Category $category,
        public array $details,
        public array $flags,
        public string $version,
    ) {}

    public function points(string $criterion): int
    {
        foreach ($this->details as $detail) {
            if ($detail->criterion === $criterion) {
                return $detail->points;
            }
        }

        return 0;
    }

    public function withFlag(string $flag): self
    {
        return new self($this->total, $this->category, $this->details, [...$this->flags, $flag], $this->version);
    }
}
