<?php

declare(strict_types=1);

namespace App\Episode;

final readonly class Script
{
    /**
     * @param list<Line> $lines
     */
    public function __construct(
        public string $title,
        public array $lines,
    ) {
    }
}
