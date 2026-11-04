<?php

declare(strict_types=1);

namespace App\Command;

use App\Episode\Brief;
use App\Episode\Mixer;
use App\Episode\ScriptWriter;
use App\Episode\Voice;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;

#[AsCommand('app:episode', 'Write, voice and mix an episode from a brief')]
final readonly class EpisodeCommand
{
    public function __construct(
        private SerializerInterface $serializer,
        private ScriptWriter $writer,
        private Voice $voice,
        private Mixer $mixer,
        private Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/var/episodes')] private string $episodesDir,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Path to a brief JSON file')] string $brief,
    ): int {
        $brief = $this->serializer->deserialize($this->filesystem->readFile($brief), Brief::class, JsonEncoder::FORMAT);
        $dir = \sprintf('%s/%s', $this->episodesDir, date('Ymd-His'));

        $start = hrtime(true);
        $script = $this->writer->write($brief);
        $this->filesystem->dumpFile("{$dir}/script.json", $this->serializer->serialize($script, JsonEncoder::FORMAT, ['json_encode_options' => \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE]));
        $io->writeln(\sprintf('Script: "%s", %d lines (%.1fs)', $script->title, \count($script->lines), (hrtime(true) - $start) / 1e9));
        foreach ($script->lines as $line) {
            $io->writeln(\sprintf('<info>%s</info> <comment>(%s) [%s]</comment> %s', $line->host, $line->emotion, implode(',', $line->sources), $line->text));
        }

        $start = hrtime(true);
        $clips = $this->voice->speak($script);
        $io->writeln(\sprintf('Voice: %d clips (%.1fs)', \count($clips), (hrtime(true) - $start) / 1e9));

        $this->mixer->mix($clips, "{$dir}/episode.mp3");
        $io->success("{$dir}/episode.mp3");

        return 0;
    }
}
