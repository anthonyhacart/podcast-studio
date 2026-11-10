<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\Episode\EpisodeRepository;
use App\Mailbox\Mailbox;
use App\Mcp\EpisodeTools;
use Mcp\Exception\ToolCallException;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;

/**
 * The whole pipeline, as an MCP client drives it, against recorded Mistral responses:
 * draft, publish, then find the episode in the show's memory. Without the cassette,
 * it records one (`make cassette`).
 */
#[Group('cassette')]
final class EpisodeToolsTest extends KernelTestCase
{
    private const BRIEF = __DIR__.'/../../fixtures/briefs/worlds-2026.json';

    protected function setUp(): void
    {
        $_SERVER['CASSETTE'] = 'fixtures/cassettes/worlds-2026.json';
        self::bootKernel();
        (new Filesystem())->remove([self::getContainer()->getParameter('app.episodes_dir'), self::getContainer()->getParameter('app.questions_dir')]);
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
        self::assertStringStartsWith('## ', $draft['script']);
        self::assertStringContainsString("\nPaul (", $draft['script']);
        self::assertIsString($draft['summary']);
        self::assertIsString($draft['next']);
        self::assertStringContainsString('publish_episode once the user approves', $draft['next']);

        $published = $tools->publish($draft['id']);

        self::assertSame($draft['id'], $published['id']);
        self::assertMatchesRegularExpression('/^\d+:\d{2}$/', $published['duration']);
        $dir = self::getContainer()->getParameter('app.episodes_dir');
        self::assertFileExists("{$dir}/{$draft['id']}/episode.mp3");
        self::assertStringStartsWith('WEBVTT', (string) file_get_contents("{$dir}/{$draft['id']}/transcript.vtt"));
        $episodes = self::getContainer()->get(EpisodeRepository::class);
        self::assertNotEmpty($episodes->script($draft['id'])->summary);
        self::assertGreaterThanOrEqual(2, \count($episodes->episode($draft['id'])->chapters));
        self::assertSame(0.0, $episodes->episode($draft['id'])->chapters[0]->start);

        $memories = $tools->search('who is favourite to win the title');

        self::assertCount(5, $memories);
        self::assertStringStartsWith("On 4 November 2026, in \"{$draft['title']}\", ", $memories[0]);
    }

    public function testListsTheListenerQuestionsWaitingForAnEpisode(): void
    {
        $question = self::getContainer()->get(Mailbox::class)->post('Who wins the final?', 'Marie');

        self::assertSame([['id' => $question->id, 'from' => 'Marie', 'question' => 'Who wins the final?', 'asked' => '4 November 2026']], self::getContainer()->get(EpisodeTools::class)->questions());
    }

    public function testRefusesToDraftWithAQuestionNoLongerWaiting(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('No listener question "01a1bdf1-396d-7f14-bb1f-e2125edaad26" is waiting.');

        self::getContainer()->get(EpisodeTools::class)->draft('Worlds', [['title' => 'Format', 'notes' => 'Final on 14 November.']], questions: ['01a1bdf1-396d-7f14-bb1f-e2125edaad26']);
    }

    public function testRefusesToPublishAnEpisodeAlreadyBeingPublished(): void
    {
        $lock = self::getContainer()->get(LockFactory::class)->createLock('publish-20261104-100000-abcd');
        self::assertTrue($lock->acquire());

        try {
            $this->expectException(ToolCallException::class);
            $this->expectExceptionMessage('already being published');

            self::getContainer()->get(EpisodeTools::class)->publish('20261104-100000-abcd');
        } finally {
            $lock->release();
        }
    }
}
