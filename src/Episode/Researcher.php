<?php

declare(strict_types=1);

namespace App\Episode;

use App\Mailbox\Question;
use SensitiveParameter;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * Finds the sources of an episode for an editor who brings none: the "researcher" agent
 * searches the web and picks pages, then the pages are read like the links an editor gives.
 * The agent only chooses: what the writer gets is the text of the pages.
 */
final readonly class Researcher
{
    public const MAX_SOURCES = 5;

    /** Pages tried before giving up on reaching MAX_SOURCES: some cannot be read. */
    private const MAX_PAGES = 8;

    public function __construct(
        #[Target('researcher')] private AgentInterface $researcher,
        private SourceReader $reader,
        private ClockInterface $clock,
        #[SensitiveParameter] #[Autowire(env: 'TAVILY_API_KEY')] private string $searchKey,
    ) {
    }

    /**
     * The agent's web search needs a key: without one, the editor brings the sources.
     */
    public function isAvailable(): bool
    {
        return '' !== $this->searchKey;
    }

    /**
     * @param list<Question> $questions listener questions the episode answers
     *
     * @return non-empty-list<Source>
     *
     * @throws NothingFoundException
     */
    public function research(string $topic, array $questions = []): array
    {
        $answer = $this->researcher->call(new MessageBag(Message::ofUser($this->request($topic, $questions))), ['response_format' => Findings::class, 'temperature' => 0.2]);

        // what the search returned, in its order: the agent's pick first, and nothing it made up
        $found = [];
        $results = $answer->getResult()->getMetadata()->get('sources');
        foreach ($results instanceof SourceCollection ? $results : [] as $source) {
            if ('' !== $source->getReference()) {
                $found[$source->getReference()] = true;
            }
        }
        $picked = array_intersect($answer->asObject(Findings::class)->urls, array_keys($found));
        $urls = \array_slice(array_unique([...$picked, ...array_keys($found)]), 0, self::MAX_PAGES);

        $sources = [];
        foreach ($urls as $url) {
            try {
                $sources[] = $this->reader->read($url);
            } catch (UnreadableSourceException) {
                continue;
            }
            if (self::MAX_SOURCES === \count($sources)) {
                break;
            }
        }

        return [] === $sources ? throw new NothingFoundException($topic) : $sources;
    }

    /**
     * @param list<Question> $questions
     */
    private function request(string $topic, array $questions): string
    {
        $request = "Today: {$this->clock->now()->format('l j F Y')}.\nTopic: {$topic}\n";
        if ([] !== $questions) {
            $request .= "Listener questions to answer on air:\n";
            foreach ($questions as $question) {
                $request .= "- {$question->text}\n";
            }
        }

        return $request;
    }
}
