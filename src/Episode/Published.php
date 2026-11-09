<?php

declare(strict_types=1);

namespace App\Episode;

use DateTimeImmutable;

final readonly class Published
{
    /**
     * @param list<Chapter> $chapters
     */
    public function __construct(
        public string $id,
        public string $title,
        public float $seconds,
        public DateTimeImmutable $publishedAt,
        public ?string $rephrased = null,
        public array $chapters = [],
    ) {
    }

    /**
     * e.g. "2:05".
     */
    public function duration(): string
    {
        return self::minutes($this->seconds);
    }

    /**
     * Seconds as "m:ss", the way players show times.
     */
    public static function minutes(float $seconds): string
    {
        return \sprintf('%d:%02d', intdiv((int) round($seconds), 60), (int) round($seconds) % 60);
    }
}
