<?php

declare(strict_types=1);

namespace App\Archive;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * The show's memory: what the hosts said in the published episodes, searched by meaning.
 * A tool of the "writer" agent, and of the MCP client through search_episodes.
 *
 * It queries the store with a VectorQuery itself: the bundle's Retriever sends a hybrid
 * query to Postgres, which only returns lines sharing a keyword with the query.
 */
#[AsTool('search_past_episodes', 'Finds what the hosts said in past episodes of this show, to refer back to it instead of repeating it.')]
final readonly class PastEpisodes
{
    public const LIMIT = 5;

    public function __construct(
        #[Target('archive')] private VectorizerInterface $vectorizer,
        #[Autowire(service: 'app.archive')] private StoreInterface $store,
    ) {
    }

    /**
     * @param string $query what to look for: a topic, a name, a claim
     *
     * @return list<string> the closest lines, like `On 4 November 2026, in "Title", Jane: …`
     */
    public function __invoke(string $query): array
    {
        $lines = [];
        foreach ($this->store->query(new VectorQuery($this->vectorizer->vectorize($query)), ['limit' => self::LIMIT]) as $document) {
            $lines[] = (string) $document->getMetadata()->getText();
        }

        return $lines;
    }
}
