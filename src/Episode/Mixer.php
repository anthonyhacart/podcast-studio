<?php

declare(strict_types=1);

namespace App\Episode;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Joins the spoken lines with short pauses and normalises loudness, with ffmpeg.
 */
final readonly class Mixer
{
    private const PAUSE_SECONDS = 0.35;

    public function __construct(
        private Filesystem $filesystem,
    ) {
    }

    /**
     * @param list<string> $clips WAV file contents, in order
     */
    public function mix(array $clips, string $mp3Path): void
    {
        $dir = $this->filesystem->tempnam(sys_get_temp_dir(), 'mix');
        $this->filesystem->remove($dir);
        $this->filesystem->mkdir($dir);

        try {
            $command = ['ffmpeg', '-v', 'error', '-y'];
            $filters = '';
            $labels = '';
            foreach ($clips as $i => $clip) {
                $this->filesystem->dumpFile($file = \sprintf('%s/%03d.wav', $dir, $i), $clip);
                array_push($command, '-i', $file);
                $filters .= \sprintf('[%d:a]aresample=24000,aformat=channel_layouts=mono,apad=pad_dur=%s[a%1$d];', $i, self::PAUSE_SECONDS);
                $labels .= "[a{$i}]";
            }
            $filters .= \sprintf('%sconcat=n=%d:v=0:a=1,loudnorm=I=-16:TP=-1.5[out]', $labels, \count($clips));
            array_push($command, '-filter_complex', $filters, '-map', '[out]', '-ac', '1', '-ar', '44100', '-b:a', '128k', $mp3Path);

            $this->filesystem->mkdir(\dirname($mp3Path));
            (new Process($command))->setTimeout(120)->mustRun();
        } finally {
            $this->filesystem->remove($dir);
        }
    }
}
