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

    /**
     * e.g. "2:05".
     */
    public function duration(): string
    {
        return \sprintf('%d:%02d', intdiv((int) round($this->seconds), 60), (int) round($this->seconds) % 60);
    }
}
