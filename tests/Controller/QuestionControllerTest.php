<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Mailbox\Mailbox;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * A listener asks by voice, against a recorded Voxtral transcription. Without
 * the cassette, it records one (`make cassette`).
 */
#[Group('cassette')]
final class QuestionControllerTest extends WebTestCase
{
    private const QUESTION = __DIR__.'/../../fixtures/audio/question.mp3';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $_SERVER['CASSETTE'] = 'fixtures/cassettes/question.json';
        $this->client = self::createClient();
        (new Filesystem())->remove(self::getContainer()->getParameter('app.questions_dir'));
    }

    protected function tearDown(): void
    {
        unset($_SERVER['CASSETTE']);
        parent::tearDown();
    }

    public function testTranscribesTheQuestionIntoTheMailbox(): void
    {
        $this->client->request('POST', '/questions', ['name' => ' Marie '], ['audio' => new UploadedFile(self::QUESTION, 'question.mp3', test: true)]);

        self::assertResponseStatusCodeSame(201);
        $waiting = self::getContainer()->get(Mailbox::class)->waiting();
        self::assertCount(1, $waiting);
        self::assertSame('Marie', $waiting[0]->name);
        self::assertStringContainsString('Who do you think will win', $waiting[0]->text);
        self::assertJsonStringEqualsJsonString((string) json_encode(['question' => $waiting[0]->text]), (string) $this->client->getResponse()->getContent());
    }

    public function testRefusesWhatIsNotAudio(): void
    {
        $this->client->request('POST', '/questions', files: ['audio' => new UploadedFile(__FILE__, 'question.mp3', test: true)]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([], self::getContainer()->get(Mailbox::class)->waiting());
    }
}
