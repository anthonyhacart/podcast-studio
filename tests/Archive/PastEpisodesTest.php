<?php

declare(strict_types=1);

namespace App\Tests\Archive;

use App\Archive\PastEpisodes;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\InMemory\Store;

final class PastEpisodesTest extends TestCase
{
    public function testFindsTheLinesClosestInMeaningToTheQuery(): void
    {
        $store = new Store();
        $store->add([
            $this->line([0.0, 1.0], 'On 4 November 2026, in "Mattresses", Jane: Foam is for people who like sinking.'),
            $this->line([1.0, 0.0], 'On 4 November 2026, in "Worlds, the knockouts", Paul: T1 will win it all, mark my words.'),
        ]);

        $search = new PastEpisodes($this->vectorizer([1.0, 0.1]), $store);

        self::assertSame([
            'On 4 November 2026, in "Worlds, the knockouts", Paul: T1 will win it all, mark my words.',
            'On 4 November 2026, in "Mattresses", Jane: Foam is for people who like sinking.',
        ], $search('who will win Worlds'));
    }

    public function testKeepsTheClosestLinesOnly(): void
    {
        $store = new Store();
        $store->add(array_map(fn (int $i) => $this->line([1.0, $i / 10], "Paul: line {$i}."), range(1, PastEpisodes::LIMIT + 2)));

        self::assertCount(PastEpisodes::LIMIT, (new PastEpisodes($this->vectorizer([1.0, 0.0]), $store))('anything'));
    }

    public function testFindsNothingInAnEmptyArchive(): void
    {
        self::assertSame([], (new PastEpisodes($this->vectorizer([1.0, 0.0]), new Store()))('anything'));
    }

    /**
     * @param list<float> $vector
     */
    private function line(array $vector, string $quote): VectorDocument
    {
        return new VectorDocument(bin2hex(random_bytes(4)), new Vector($vector), new Metadata([Metadata::KEY_TEXT => $quote]));
    }

    /**
     * @param list<float> $vector what every query is embedded as
     */
    private function vectorizer(array $vector): VectorizerInterface
    {
        $vectorizer = self::createStub(VectorizerInterface::class);
        $vectorizer->method('vectorize')->willReturn(new Vector($vector));

        return $vectorizer;
    }
}
