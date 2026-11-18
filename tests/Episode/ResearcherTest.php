<?php

declare(strict_types=1);

namespace App\Tests\Episode;

use App\Episode\Findings;
use App\Episode\NothingFoundException;
use App\Episode\Researcher;
use App\Episode\SourceReader;
use App\Mailbox\Question;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\ExecutionInterface;
use Symfony\AI\Agent\Toolbox\Source\Source as Found;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ResearcherTest extends TestCase
{
    private const FOUND = ['https://a.example/format', 'https://b.example/teams', 'https://c.example/forum'];

    private ?MessageBag $asked = null;

    public function testReadsThePagesTheAgentPickedFirstThenTheOtherResults(): void
    {
        $sources = $this->researcher(picked: ['https://b.example/teams'])->research('Worlds 2026');

        self::assertSame(['https://b.example/teams', 'https://a.example/format', 'https://c.example/forum'], array_column($sources, 'url'));
        self::assertSame('Page /teams', $sources[0]->title);
        self::assertSame('Facts of /teams.', $sources[0]->notes);
    }

    public function testIgnoresAPageTheSearchDidNotReturn(): void
    {
        $sources = $this->researcher(picked: ['https://made-up.example/', 'https://a.example/format'])->research('Worlds 2026');

        self::assertSame(self::FOUND, array_column($sources, 'url'));
    }

    public function testSkipsThePagesItCannotRead(): void
    {
        $sources = $this->researcher(picked: self::FOUND, unreadable: ['https://a.example/format'])->research('Worlds 2026');

        self::assertSame(['https://b.example/teams', 'https://c.example/forum'], array_column($sources, 'url'));
    }

    public function testKeepsFiveSourcesAtMost(): void
    {
        $found = array_map(static fn (int $i) => "https://a.example/{$i}", range(1, 12));

        self::assertCount(Researcher::MAX_SOURCES, $this->researcher(picked: [], found: $found)->research('Worlds 2026'));
    }

    public function testTellsWhenNothingReadableWasFound(): void
    {
        $this->expectException(NothingFoundException::class);
        $this->expectExceptionMessage('The hosts found nothing they could read about "Worlds 2026". Give them a link or some notes.');

        $this->researcher(picked: self::FOUND, unreadable: self::FOUND)->research('Worlds 2026');
    }

    public function testAsksWithTheDateAndTheListenerQuestions(): void
    {
        $this->researcher(picked: self::FOUND)->research('Worlds 2026', [new Question('01a1bdf1-396d-7f14-bb1f-e2125edaad26', 'Who wins the final?', new DateTimeImmutable('2026-11-03'), 'Marie')]);

        self::assertSame("Today: Wednesday 4 November 2026.\nTopic: Worlds 2026\nListener questions to answer on air:\n- Who wins the final?\n", $this->asked?->latestAs(Role::User)?->asText());
    }

    public function testIsOnlyAvailableWithAKeyForItsWebSearch(): void
    {
        $reader = new SourceReader(new MockHttpClient());

        self::assertTrue(new Researcher(self::createStub(AgentInterface::class), $reader, new MockClock(), 'a-key')->isAvailable());
        self::assertFalse(new Researcher(self::createStub(AgentInterface::class), $reader, new MockClock(), '')->isAvailable());
    }

    /**
     * @param list<string> $picked     the URLs the agent answers
     * @param list<string> $found      the URLs its web search returned
     * @param list<string> $unreadable those of them answering with an error
     */
    private function researcher(array $picked, array $found = self::FOUND, array $unreadable = []): Researcher
    {
        $result = new TextResult('');
        $result->getMetadata()->add('sources', new SourceCollection(array_map(static fn (string $url) => new Found('A result', $url, ''), $found)));

        $execution = self::createStub(ExecutionInterface::class);
        $execution->method('getResult')->willReturn($result);
        $execution->method('asObject')->willReturn(new Findings($picked));

        $agent = self::createStub(AgentInterface::class);
        $agent->method('call')->willReturnCallback(function (MessageBag $messages) use ($execution): ExecutionInterface {
            $this->asked = $messages;

            return $execution;
        });

        $web = new MockHttpClient(static function (string $method, string $url) use ($unreadable): MockResponse {
            $path = (string) parse_url($url, \PHP_URL_PATH);

            return \in_array($url, $unreadable, true)
                ? new MockResponse('Gone', ['http_code' => 410])
                : new MockResponse("<html><head><title>Page {$path}</title></head><body><p>Facts of {$path}.</p></body></html>", ['response_headers' => ['Content-Type' => 'text/html']]);
        });

        return new Researcher($agent, new SourceReader($web), new MockClock('2026-11-04 10:00'), 'a-key');
    }
}
