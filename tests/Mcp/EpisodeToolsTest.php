<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\Mcp\EpisodeTools;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The whole pipeline, as an MCP client drives it, against recorded Mistral responses.
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

    public function testDraftsThenPublishesAnEpisode(): void
    {
        $tools = self::getContainer()->get(EpisodeTools::class);
        /** @var array{topic: string, sources: list<array{title: string, notes: string, url?: string}>, minutes: int, tone: string} $brief */
        $brief = json_decode((string) file_get_contents(self::BRIEF), true, flags: \JSON_THROW_ON_ERROR);

        $draft = $tools->draft($brief['topic'], $brief['sources'], $brief['minutes'], $brief['tone']);

        self::assertIsString($draft['id']);
        self::assertSame('Knockout Stage Kicks Off at Worlds 2026', $draft['title']);
        self::assertIsString($draft['script']);
        self::assertStringStartsWith('Paul (', $draft['script']);
        self::assertIsString($draft['next']);
        self::assertStringContainsString('publish_episode once the user approves', $draft['next']);

        $published = $tools->publish($draft['id']);

        self::assertSame(['id' => $draft['id'], 'title' => $draft['title'], 'duration' => '1:34', 'status' => 'published'], $published);
        $dir = self::getContainer()->getParameter('app.episodes_dir');
        self::assertFileExists("{$dir}/{$draft['id']}/episode.mp3");
        self::assertStringStartsWith('WEBVTT', (string) file_get_contents("{$dir}/{$draft['id']}/transcript.vtt"));
    }
}
