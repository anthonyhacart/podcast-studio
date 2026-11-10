<?php

declare(strict_types=1);

namespace App\Command;

use App\Episode\Brief;
use App\Episode\Studio;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;

#[AsCommand('app:episode', 'Draft and publish an episode from a brief, without review')]
final readonly class EpisodeCommand
{
    public function __construct(
        private SerializerInterface $serializer,
        private Filesystem $filesystem,
        private Studio $studio,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Path to a brief JSON file')] string $brief,
    ): int {
        $brief = $this->serializer->deserialize($this->filesystem->readFile($brief), Brief::class, JsonEncoder::FORMAT);

        [$id, $script] = $this->studio->draft($brief);
        $io->writeln(\sprintf('Script "%s", %d lines', $script->title, \count($script->lines)));
        foreach ($script->lines as $line) {
            $io->writeln(\sprintf('<info>%s</info> <comment>(%s) [%s]</comment> %s', $line->host, $line->emotion, implode(',', $line->sources), $line->text));
        }

        $episode = $this->studio->publish($id);
        $io->success(\sprintf('Episode %s published (%s)', $episode->id, $episode->duration()));

        return Command::SUCCESS;
    }
}
