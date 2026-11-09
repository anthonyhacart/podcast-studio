<?php

declare(strict_types=1);

namespace App\Episode;

use App\Mailbox\Question;

final readonly class Brief
{
    /**
     * @param list<Source>   $sources
     * @param list<Question> $questions listener questions the episode answers
     */
    public function __construct(
        public string $topic,
        public array $sources,
        public int $minutes = 2,
        public ?string $tone = null,
        public array $questions = [],
    ) {
    }
}
