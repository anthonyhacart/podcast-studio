<?php

declare(strict_types=1);

namespace App\Episode;

use InvalidArgumentException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * One folder per episode: brief.json, script.json, then episode.mp3, transcript.vtt and episode.json once published.
 */
final readonly class EpisodeRepository
{
    private const ID = '/^\d{8}-\d{6}-[0-9a-f]{4}$/';

    public function __construct(
        private Filesystem $filesystem,
        private SerializerInterface $serializer,
        private ClockInterface $clock,
        #[Autowire(param: 'app.episodes_dir')] private string $dir,
    ) {
    }

    public function nextId(): string
    {
        return $this->clock->now()->format('Ymd-His').'-'.bin2hex(random_bytes(2));
    }

    public function saveBrief(string $id, Brief $brief): void
    {
        $this->write($id, 'brief.json', $brief);
    }

    public function brief(string $id): Brief
    {
        return $this->read($id, 'brief.json', Brief::class);
    }

    public function saveScript(string $id, Script $script): void
    {
        $this->write($id, 'script.json', $script);
    }

    public function script(string $id): Script
    {
        return $this->read($id, 'script.json', Script::class);
    }

    public function savePublished(string $id, Published $episode, string $mp3, string $transcript): void
    {
        $this->filesystem->dumpFile($this->path($id, 'episode.mp3'), $mp3);
        $this->filesystem->dumpFile($this->path($id, 'transcript.vtt'), $transcript);
        $this->write($id, 'episode.json', $episode);
    }

    public function episode(string $id): Published
    {
        return $this->read($id, 'episode.json', Published::class);
    }

    /**
     * @return list<Published> newest first
     */
    public function published(): array
    {
        $episodes = [];
        foreach (glob("{$this->dir}/*/episode.json") ?: [] as $file) {
            $episodes[] = $this->episode(basename(\dirname($file)));
        }
        usort($episodes, static fn (Published $a, Published $b) => $b->publishedAt <=> $a->publishedAt);

        return $episodes;
    }

    public function audioPath(string $id): string
    {
        return $this->publishedFile($id, 'episode.mp3');
    }

    public function transcriptPath(string $id): string
    {
        return $this->publishedFile($id, 'transcript.vtt');
    }

    private function publishedFile(string $id, string $file): string
    {
        $path = $this->path($id, $file);
        if (!$this->filesystem->exists($path)) {
            throw new InvalidArgumentException(\sprintf('Episode "%s" is not published.', $id));
        }

        return $path;
    }

    private function write(string $id, string $file, object $data): void
    {
        $this->filesystem->dumpFile($this->path($id, $file), $this->serializer->serialize($data, JsonEncoder::FORMAT, [
            'json_encode_options' => \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES,
        ]));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function read(string $id, string $file, string $class): object
    {
        $path = $this->path($id, $file);
        if (!$this->filesystem->exists($path)) {
            throw new InvalidArgumentException(\sprintf('Episode "%s" has no %s.', $id, $file));
        }

        $data = $this->serializer->deserialize($this->filesystem->readFile($path), $class, JsonEncoder::FORMAT);
        if (!$data instanceof $class) {
            throw new InvalidArgumentException(\sprintf('Episode "%s" has an unreadable %s.', $id, $file));
        }

        return $data;
    }

    private function path(string $id, string $file): string
    {
        // the id comes from the MCP client: never let it reach the filesystem unchecked
        if (!preg_match(self::ID, $id)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not an episode id.', $id));
        }

        return "{$this->dir}/{$id}/{$file}";
    }
}
