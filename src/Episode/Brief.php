<?php

declare(strict_types=1);

namespace App\Episode;

use App\Mailbox\Question;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What an editor asks the hosts for. Its rules hold wherever it comes from: the form
 * in the browser, an MCP client or a file (Studio::draft() checks them).
 */
final readonly class Brief
{
    /**
     * @param list<Source>   $sources
     * @param list<Question> $questions listener questions the episode answers
     * @param list<string>   $hosts     ids of the two hosts, none for the show's usual duo (Show::cast())
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $topic,
        #[Assert\Count(min: 1, minMessage: 'The hosts need a source: they only use the facts found there.')]
        #[Assert\Valid]
        public array $sources,
        #[Assert\Range(min: 1, max: 5)]
        public int $minutes = 2,
        #[Assert\Length(max: 100)]
        public ?string $tone = null,
        public array $questions = [],
        public array $hosts = [],
    ) {
    }
}
