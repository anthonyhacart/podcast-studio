<?php

declare(strict_types=1);

namespace App\Episode;

use DateTimeImmutable;

final readonly class Published
{
    public function __construct(
        public string $id,
        public string $title,
        public float $seconds,
        public DateTimeImmutable $publishedAt,
        public ?string $rephrased = null,
    ) {
    }
}
