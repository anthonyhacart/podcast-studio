<?php

declare(strict_types=1);

namespace App\Episode;

use App\Mailbox\Question;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * The form an editor without an MCP client fills in to ask for an episode (Briefing makes a brief of it).
 */
final class NewEpisode
{
    public const TWO_HOSTS = 'An episode is a dialogue: choose two hosts.';
    public const NO_SOURCE = 'Give the hosts a link or some notes, or let them search: they only use the facts found there.';

    /**
     * @param list<string>    $links     pages the hosts should read
     * @param string|null     $notes     facts of the editor's own, or the text of a page the hosts cannot read
     * @param array<Question> $questions listener questions to answer on air
     * @param bool            $search    whether the hosts search the web for their sources themselves
     * @param list<string>    $hosts     ids of the two hosts of the episode
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $topic = '',
        #[Assert\Count(max: 5, maxMessage: 'The hosts read five links at most.')]
        #[Assert\All([new Assert\Url(message: '{{ value }} is not a link the hosts can read.', protocols: ['http', 'https'], requireTld: true)])]
        public array $links = [],
        #[Assert\Length(max: SourceReader::MAX_CHARACTERS)]
        public ?string $notes = null,
        #[Assert\Range(min: 1, max: 5)]
        public int $minutes = 2,
        #[Assert\Length(max: 100)]
        public ?string $tone = null,
        public array $questions = [],
        public bool $search = false,
        #[Assert\Count(exactly: 2, exactMessage: self::TWO_HOSTS)]
        public array $hosts = [],
    ) {
    }

    #[Assert\Callback]
    public function needsASource(ExecutionContextInterface $context): void
    {
        if (!$this->search && [] === $this->links && null === $this->notes) {
            $context->buildViolation(self::NO_SOURCE)->atPath('links')->addViolation();
        }
    }
}
