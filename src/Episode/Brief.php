<?php

declare(strict_types=1);

namespace App\Episode;

final readonly class Brief
{
    /**
     * @param list<Source> $sources
     */
    public function __construct(
        public string $topic,
        public array $sources,
        public int $minutes = 2,
        public ?string $tone = null,
    ) {
    }
}
