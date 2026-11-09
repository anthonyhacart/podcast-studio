<?php

declare(strict_types=1);

namespace App\Tests\Mailbox;

use App\Mailbox\Mailbox;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class MailboxTest extends TestCase
{
    private string $dir;
    private MockClock $clock;
    private Mailbox $mailbox;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/questions-'.bin2hex(random_bytes(4));
        $this->clock = new MockClock('2026-11-04 10:00:00');
        $serializer = new Serializer([new DateTimeNormalizer(), new ObjectNormalizer(propertyTypeExtractor: new PhpStanExtractor())], [new JsonEncoder()]);
        $this->mailbox = new Mailbox(new Filesystem(), $serializer, $this->clock, $this->dir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    public function testKeepsAQuestionUntilAnEpisodeAnswersIt(): void
    {
        $posted = $this->mailbox->post('Who wins the final?', 'Marie');

        $question = $this->mailbox->question($posted->id);

        self::assertSame('Who wins the final?', $question->text);
        self::assertSame('Marie', $question->name);
        self::assertSame('2026-11-04 10:00', $question->askedAt->format('Y-m-d H:i'));
    }

    public function testListsTheWaitingQuestionsOldestFirst(): void
    {
        $this->mailbox->post('First?');
        $this->clock->sleep(60);
        $answered = $this->mailbox->post('Second?');
        $this->clock->sleep(60);
        $this->mailbox->post('Third?');

        $this->mailbox->remove([$answered]);

        self::assertSame(['First?', 'Third?'], array_map(static fn ($q) => $q->text, $this->mailbox->waiting()));
    }

    #[TestWith(['../../etc/passwd'])]
    #[TestWith(['01a1bdf1-396d-7f14-bb1f-e2125edaad26'])]
    public function testRefusesUnknownOrForgedIds(string $id): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->mailbox->question($id);
    }
}
