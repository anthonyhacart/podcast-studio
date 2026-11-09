<?php

declare(strict_types=1);

namespace App\Episode;

/**
 * A chapter of a published episode, for podcast apps and the episode page.
 */
final readonly class Chapter
{
    public function __construct(
        public string $title,
        public float $start,
    ) {
    }

    /**
     * e.g. "0:42".
     */
    public function at(): string
    {
        return Published::minutes($this->start);
    }

    /**
     * @param list<array{float, float}> $timings when each line starts and ends, in seconds
     *
     * @return list<self> the chapters the lines open, with the time they start
     */
    public static function fromScript(Script $script, array $timings): array
    {
        $chapters = [];
        foreach ($script->lines as $i => $line) {
            if (null !== $line->chapter) {
                $chapters[] = new self($line->chapter, $timings[$i][0]);
            }
        }

        return $chapters;
    }
}
