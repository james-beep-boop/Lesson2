<?php

namespace App\Services;

readonly class SelectionMatchResult
{
    private function __construct(
        public bool $confident,
        public int $start,
        public int $end,
    ) {}

    public static function confident(int $start, int $end): self
    {
        return new self(true, $start, $end);
    }

    public static function ambiguous(): self
    {
        return new self(false, 0, 0);
    }
}
