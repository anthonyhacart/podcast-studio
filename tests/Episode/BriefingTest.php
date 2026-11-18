<?php

declare(strict_types=1);

namespace App\Tests\Episode;

use App\Episode\Briefing;
use App\Episode\Findings;
use App\Episode\NewEpisode;
use App\Episode\Researcher;
use App\Episode\SourceReader;
use App\Mailbox\Question;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\ExecutionInterface;
use Symfony\AI\Agent\Toolbox\Source\Source as Found;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BriefingTest extends TestCase
{
    public function testReadsTheLinksAndKeepsTheNotesAsASource(): void
    {
        $episode = new NewEpisode();
        $episode->topic = 'Worlds 2026';
        $episode->links = ['https://a.example/format'];
        $episode->notes = 'T1 are the defending champions.';
        $episode->minutes = 3;
        $episode->tone = 'fun';
        $episode->questions = [3 => $question = new Question('01a1bdf1-396d-7f14-bb1f-e2125edaad26', 'Who wins the final?', new DateTimeImmutable('2026-11-03'), 'Marie')];

        $brief = $this->briefing()->brief($episode);

        self::assertSame('Worlds 2026', $brief->topic);
        self::assertSame(['https://a.example/format', null], array_column($brief->sources, 'url'));
        self::assertSame(['Page /format', 'Notes from the editor'], array_column($brief->sources, 'title'));
        self::assertSame('T1 are the defending champions.', $brief->sources[1]->notes);
        self::assertSame(3, $brief->minutes);
        self::assertSame('fun', $brief->tone);
        self::assertSame([$question], $brief->questions);
    }

    public function testAddsWhatTheResearcherFoundToTheLinksOfTheEditor(): void
    {
        $episode = new NewEpisode(search: true);
        $episode->topic = 'Worlds 2026';
        $episode->links = ['https://a.example/format'];

        $brief = $this->briefing(found: ['https://a.example/format', 'https://b.example/teams'])->brief($episode);

        self::assertSame(['https://a.example/format', 'https://b.example/teams'], array_column($brief->sources, 'url'));
    }

    /**
     * @param list<string> $found the pages the researcher's agent found and picked
     */
    private function briefing(array $found = []): Briefing
    {
        $answer = new TextResult('');
        $answer->getMetadata()->add('sources', new SourceCollection(array_map(static fn (string $url) => new Found('A result', $url, ''), $found)));
        $execution = self::createStub(ExecutionInterface::class);
        $execution->method('getResult')->willReturn($answer);
        $execution->method('asObject')->willReturn(new Findings($found));
        $agent = self::createStub(AgentInterface::class);
        $agent->method('call')->willReturn($execution);

        $reader = new SourceReader(new MockHttpClient(static fn (string $method, string $url) => new MockResponse(
            \sprintf('<html><head><title>Page %s</title></head><body><p>Facts.</p></body></html>', parse_url($url, \PHP_URL_PATH)),
            ['response_headers' => ['Content-Type' => 'text/html']],
        )));

        return new Briefing($reader, new Researcher($agent, $reader, new MockClock(), 'a-key'));
    }
}
