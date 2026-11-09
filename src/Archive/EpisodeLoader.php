<?php

declare(strict_types=1);

namespace App\Archive;

use App\Episode\EpisodeRepository;
use App\Episode\Published;
use App\Show\Show;
use Symfony\AI\Store\Document\LoaderInterface;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\Component\Uid\Uuid;

/**
 * Turns a published episode into one document per line, a quote saying when and where it
 * was said, for the "archive" indexer.
 * Without a source, it loads every published episode ("ai:store:index archive").
 */
final readonly class EpisodeLoader implements LoaderInterface
{
    private const NAMESPACE = 'b2f7c1d4-3c1a-4f0e-9a52-6d1f0c8e7a10';

    public function __construct(
        private EpisodeRepository $episodes,
        private Show $show,
    ) {
    }

    /**
     * @param string|null $source an episode id
     *
     * @return iterable<TextDocument>
     */
    public function load(?string $source = null, array $options = []): iterable
    {
        $episodes = null === $source ? $this->episodes->published() : [$this->episodes->episode($source)];

        foreach ($episodes as $episode) {
            yield from $this->documents($episode);
        }
    }

    /**
     * @return iterable<TextDocument>
     */
    private function documents(Published $episode): iterable
    {
        foreach ($this->episodes->script($episode->id)->lines as $i => $line) {
            $quote = \sprintf('On %s, in "%s", %s: %s', $episode->publishedAt->format('j F Y'), $episode->title, $this->show->hosts[$line->host]->name ?? $line->host, $line->text);

            // the same line always gets the same id, so indexing an episode again replaces its lines
            yield new TextDocument(Uuid::v5(Uuid::fromString(self::NAMESPACE), "{$episode->id}/{$i}")->toRfc4122(), $quote, new Metadata([Metadata::KEY_TEXT => $quote]));
        }
    }
}
