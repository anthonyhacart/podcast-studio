<?php

declare(strict_types=1);

namespace App\Tests\Archive;

use App\Archive\EpisodeLoader;
use App\Episode\EpisodeRepository;
use App\Episode\Line;
use App\Episode\Published;
use App\Episode\Script;
use App\Tests\Episode\Fixtures;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class EpisodeLoaderTest extends TestCase
{
    private string $dir;
    private EpisodeRepository $episodes;
    private EpisodeLoader $loader;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/episodes-'.bin2hex(random_bytes(4));
        $this->episodes = Fixtures::episodes($this->dir);
        $this->loader = new EpisodeLoader($this->episodes, Fixtures::show());
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    public function testMakesADocumentOfEachLineOfAnEpisode(): void
    {
        $this->publish('20261104-100000-aaaa', 'Worlds, the knockouts', ['T1 are through.', 'Not for long.']);

        $documents = [...$this->loader->load('20261104-100000-aaaa')];

        self::assertCount(2, $documents);
        self::assertSame('On 4 November 2026, in "Worlds, the knockouts", Paul: T1 are through.', $documents[0]->getContent());
        self::assertSame('On 4 November 2026, in "Worlds, the knockouts", Jane: Not for long.', $documents[1]->getMetadata()->getText());
    }

    public function testGivesALineTheSameIdEveryTimeSoIndexingAgainReplacesIt(): void
    {
        $this->publish('20261104-100000-aaaa', 'Worlds', ['T1 are through.', 'Not for long.']);

        $ids = $this->ids();

        self::assertSame($ids, $this->ids());
        self::assertCount(2, array_unique($ids));
    }

    public function testLoadsEveryPublishedEpisodeWithoutASource(): void
    {
        $this->publish('20261104-100000-aaaa', 'Worlds', ['T1 are through.']);
        $this->publish('20261105-100000-bbbb', 'Mattresses', ['Foam or springs?', 'Foam.']);
        $this->episodes->saveScript('20261106-100000-cccc', new Script('A draft', [new Line('paul', 'neutral', 'Not published.', [1])]));

        self::assertCount(3, [...$this->loader->load()]);
    }

    /**
     * @return list<int|string>
     */
    private function ids(): array
    {
        $ids = [];
        foreach ($this->loader->load('20261104-100000-aaaa') as $document) {
            $ids[] = $document->getId();
        }

        return $ids;
    }

    /**
     * @param list<string> $texts said by Paul and Jane in turn
     */
    private function publish(string $id, string $title, array $texts): void
    {
        $lines = array_map(static fn (int $i, string $text) => new Line(0 === $i % 2 ? 'paul' : 'jane', 'neutral', $text, [1]), array_keys($texts), $texts);
        $this->episodes->saveScript($id, new Script($title, $lines));
        $this->episodes->savePublished($id, new Published($id, $title, 10.0, new DateTimeImmutable('2026-11-04 10:00')), '', '');
    }
}
