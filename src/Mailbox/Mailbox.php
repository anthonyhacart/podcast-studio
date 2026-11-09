<?php

declare(strict_types=1);

namespace App\Mailbox;

use InvalidArgumentException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The listeners' questions waiting for an episode, one JSON file each. A question
 * leaves the mailbox once an episode answering it is published.
 */
final readonly class Mailbox
{
    public function __construct(
        private Filesystem $filesystem,
        private SerializerInterface $serializer,
        private ClockInterface $clock,
        #[Autowire(param: 'app.questions_dir')] private string $dir,
    ) {
    }

    public function post(string $text, ?string $name = null): Question
    {
        $question = new Question(Uuid::v7()->toRfc4122(), $text, $this->clock->now(), $name);
        $this->filesystem->dumpFile($this->path($question->id), $this->serializer->serialize($question, JsonEncoder::FORMAT, [
            'json_encode_options' => \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES,
        ]));

        return $question;
    }

    public function question(string $id): Question
    {
        $path = $this->path($id);
        if (!$this->filesystem->exists($path)) {
            throw new InvalidArgumentException(\sprintf('No listener question "%s" is waiting.', $id));
        }

        return $this->serializer->deserialize($this->filesystem->readFile($path), Question::class, JsonEncoder::FORMAT);
    }

    /**
     * @return list<Question> oldest first
     */
    public function waiting(): array
    {
        $questions = [];
        foreach (glob("{$this->dir}/*.json") ?: [] as $file) {
            $questions[] = $this->question(basename($file, '.json'));
        }
        usort($questions, static fn (Question $a, Question $b) => $a->askedAt <=> $b->askedAt);

        return $questions;
    }

    /**
     * @param list<Question> $questions
     */
    public function remove(array $questions): void
    {
        foreach ($questions as $question) {
            $this->filesystem->remove($this->path($question->id));
        }
    }

    private function path(string $id): string
    {
        // the id comes from the MCP client: never let it reach the filesystem unchecked
        if (!Uuid::isValid($id)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a question id.', $id));
        }

        return "{$this->dir}/{$id}.json";
    }
}
