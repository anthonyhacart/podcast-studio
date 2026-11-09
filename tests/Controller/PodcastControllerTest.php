<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Episode\EpisodeRepository;
use App\Episode\Published;
use App\Episode\Transcript;
use App\Mailbox\Mailbox;
use App\Tests\Episode\Fixtures;
use DateTimeImmutable;
use SimpleXMLElement;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;

final class PodcastControllerTest extends WebTestCase
{
    private const ID = '20261104-100000-abcd';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        (new Filesystem())->remove([self::getContainer()->getParameter('app.episodes_dir'), self::getContainer()->getParameter('app.questions_dir')]);
    }

    public function testHomeInvitesToConnectAnMcpClientWhenThereIsNoEpisode(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.empty', 'Connect your MCP client to http://localhost/mcp');
    }

    public function testHomeListsPublishedEpisodes(): void
    {
        $this->publish();
        $this->client->request('GET', '/');

        self::assertSelectorTextContains('.episode .card-title', 'Worlds, the knockouts');
        self::assertSelectorTextContains('.episode .card-subtitle', '4 November 2026');
        self::assertSelectorTextContains('.episode .card-subtitle .badge', '2:05');
        self::assertSelectorTextContains('#transcript-'.self::ID.'-collapse', 'Paul');
        self::assertSelectorExists('audio[src="/episodes/'.self::ID.'.mp3"]');
    }

    public function testHomeInvitesListenersToAskAndShowsTheWaitingQuestions(): void
    {
        self::getContainer()->get(Mailbox::class)->post('Who wins the final?', 'Marie');
        $this->client->request('GET', '/');

        self::assertSelectorExists('[data-controller="recorder"][data-recorder-url-value="/questions"]');
        self::assertSelectorTextContains('.questions li', 'Marie: Who wins the final?');
    }

    public function testFeedDescribesEachEpisodeForPodcastApps(): void
    {
        $this->publish();
        $this->client->request('GET', '/feed.xml');

        self::assertResponseHeaderSame('Content-Type', 'application/rss+xml; charset=UTF-8');
        $rss = new SimpleXMLElement((string) $this->client->getResponse()->getContent());
        $item = $rss->channel->item[0];
        self::assertNotNull($item);
        self::assertSame('Podcast Studio', (string) $rss->channel->title);
        self::assertSame('Worlds, the knockouts', (string) $item->title);
        self::assertSame('http://localhost/episodes/'.self::ID.'.mp3', (string) $item->enclosure['url']);
        self::assertSame((string) filesize(Fixtures::PAUSE), (string) $item->enclosure['length']);
        self::assertSame('125', (string) $item->children('itunes', true)->duration);
        self::assertSame('http://localhost/episodes/'.self::ID.'.vtt', (string) $item->children('podcast', true)->transcript->attributes()['url']);
        self::assertStringContainsString('<li>Format</li>', (string) $item->description);
    }

    public function testServesTheAudioInRangesSoPodcastAppsCanSeek(): void
    {
        $this->publish();
        $this->client->request('GET', '/episodes/'.self::ID.'.mp3', server: ['HTTP_RANGE' => 'bytes=0-99']);

        self::assertResponseStatusCodeSame(206);
        self::assertResponseHeaderSame('Content-Type', 'audio/mpeg');
        self::assertResponseHeaderSame('Content-Length', '100');
    }

    public function testServesTheTranscript(): void
    {
        $this->publish();
        $this->client->request('GET', '/episodes/'.self::ID.'.vtt');

        self::assertResponseHeaderSame('Content-Type', 'text/vtt; charset=UTF-8');
        self::assertStringStartsWith("WEBVTT\n\n1\n00:00:00.000 --> 00:00:12.000\n<v Paul>", $this->client->getInternalResponse()->getContent());
    }

    public function testUnknownEpisodesAreNotFound(): void
    {
        $this->client->request('GET', '/episodes/20261104-100000-ffff.mp3');

        self::assertResponseStatusCodeSame(404);
    }

    private function publish(): void
    {
        $episodes = self::getContainer()->get(EpisodeRepository::class);
        $script = Fixtures::script();
        $episodes->saveBrief(self::ID, Fixtures::brief());
        $episodes->saveScript(self::ID, $script);
        $timings = array_map(static fn (int $i) => [$i * 12.5, $i * 12.5 + 12.0], array_keys($script->lines));
        $episodes->savePublished(
            self::ID,
            new Published(self::ID, 'Worlds, the knockouts', 125.0, new DateTimeImmutable('2026-11-04 10:00')),
            (string) file_get_contents(Fixtures::PAUSE),
            (new Transcript(Fixtures::show()))->vtt($script, $timings),
        );
    }
}
