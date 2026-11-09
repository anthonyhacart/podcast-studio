<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\Mcp\EpisodeTools;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The whole pipeline, as an MCP client drives it, against recorded Mistral responses:
 * draft, publish, then find the episode in the show's memory. Without the cassette,
 * it records one (`make cassette`).
 */
final class EpisodeToolsTest extends KernelTestCase
{
    private const BRIEF = __DIR__.'/../../fixtures/briefs/worlds-2026.json';

    protected function setUp(): void
    {
        $_SERVER['CASSETTE'] = 'fixtures/cassettes/worlds-2026.json';
        self::bootKernel();
        (new Filesystem())->remove(self::getContainer()->getParameter('app.episodes_dir'));
    }

    protected function tearDown(): void
    {
        unset($_SERVER['CASSETTE']);
        parent::tearDown();
    }

    public function testDraftsPublishesThenRemembersAnEpisode(): void
    {
        $tools = self::getContainer()->get(EpisodeTools::class);
        /** @var array{topic: string, sources: list<array{title: string, notes: string, url?: string}>, minutes: int, tone: string} $brief */
        $brief = json_decode((string) file_get_contents(self::BRIEF), true, flags: \JSON_THROW_ON_ERROR);

        $draft = $tools->draft($brief['topic'], $brief['sources'], $brief['minutes'], $brief['tone']);

        self::assertIsString($draft['id']);
        self::assertIsString($draft['title']);
        self::assertIsString($draft['script']);
        self::assertStringStartsWith('Paul (', $draft['script']);
        self::assertIsString($draft['next']);
        self::assertStringContainsString('publish_episode once the user approves', $draft['next']);

        $published = $tools->publish($draft['id']);

        self::assertSame($draft['id'], $published['id']);
        self::assertMatchesRegularExpression('/^\d+:\d{2}$/', $published['duration']);
        $dir = self::getContainer()->getParameter('app.episodes_dir');
        self::assertFileExists("{$dir}/{$draft['id']}/episode.mp3");
        self::assertStringStartsWith('WEBVTT', (string) file_get_contents("{$dir}/{$draft['id']}/transcript.vtt"));

        $memories = $tools->search('who is favourite to win the title');

        self::assertCount(5, $memories);
        self::assertStringStartsWith("On 4 November 2026, in \"{$draft['title']}\", ", $memories[0]);
    }
}
