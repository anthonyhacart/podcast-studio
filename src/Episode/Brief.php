<?php

declare(strict_types=1);

namespace App\Episode;

use App\Mailbox\Question;

final readonly class Brief
{
    /**
     * @param list<Source>   $sources
     * @param list<Question> $questions listener questions the episode answers
     * @param list<string>   $hosts     ids of the two hosts, none for the show's usual duo (Show::cast())
     */
    public function __construct(
        public string $topic,
        public array $sources,
        public int $minutes = 2,
        public ?string $tone = null,
        public array $questions = [],
        public array $hosts = [],
    ) {
    }
}
