<?php

declare(strict_types=1);

namespace App\Episode;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Joins MP3 clips by concatenating their MPEG frames, with a short pause between
 * clips; no ffmpeg needed. Clips must share a sample rate (Voxtral: 22,050 Hz mono).
 */
final readonly class Mp3Joiner
{
    private const SAMPLE_RATES = [3 => [44100, 48000, 32000], 2 => [22050, 24000, 16000], 0 => [11025, 12000, 8000]];
    private const BITRATES_V1 = [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320];
    private const BITRATES_V2 = [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160];

    public function __construct(
        private Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/resources/audio/pause.mp3')] private string $pausePath,
    ) {
    }

    /**
     * @param list<string> $clips MP3 file contents, in order
     *
     * @return array{string, float} the joined MP3 and its duration in seconds
     */
    public function join(array $clips): array
    {
        [$pause, $pauseSeconds] = $this->frames($this->filesystem->readFile($this->pausePath));
        $mp3 = '';
        $seconds = 0.0;
        foreach ($clips as $i => $clip) {
            [$frames, $duration] = $this->frames($clip);
            $mp3 .= ($i > 0 ? $pause : '').$frames;
            $seconds += ($i > 0 ? $pauseSeconds : 0.0) + $duration;
        }

        return [$mp3, $seconds];
    }

    /**
     * Strips the ID3v2 tag and the Xing/Info header frame (it would carry the
     * clip's own length), keeps the audio frames and adds up their duration.
     *
     * @return array{string, float}
     */
    private function frames(string $mp3): array
    {
        if (str_starts_with($mp3, 'ID3')) {
            $mp3 = substr($mp3, 10 + ((\ord($mp3[6]) << 21) | (\ord($mp3[7]) << 14) | (\ord($mp3[8]) << 7) | \ord($mp3[9])));
        }

        $frames = '';
        $seconds = 0.0;
        for ($pos = 0, $length = \strlen($mp3); $pos + 4 <= $length;) {
            $header = (\ord($mp3[$pos]) << 24) | (\ord($mp3[$pos + 1]) << 16) | (\ord($mp3[$pos + 2]) << 8) | \ord($mp3[$pos + 3]);
            if (0xFFE00000 !== ($header & 0xFFE00000)) {
                ++$pos;
                continue;
            }

            $version = ($header >> 19) & 3;
            $rate = self::SAMPLE_RATES[$version][($header >> 10) & 3] ?? throw new InvalidArgumentException("Unsupported MP3 frame at byte {$pos}.");
            $bitrate = 1000 * (3 === $version ? self::BITRATES_V1 : self::BITRATES_V2)[($header >> 12) & 15];
            $samples = 3 === $version ? 1152 : 576;
            $size = intdiv(intdiv($samples, 8) * $bitrate, $rate) + (($header >> 9) & 1);
            if ($size <= 4) {
                ++$pos;
                continue;
            }

            $frame = substr($mp3, $pos, $size);
            if (!str_contains(substr($frame, 0, 48), 'Xing') && !str_contains(substr($frame, 0, 48), 'Info')) {
                $frames .= $frame;
                $seconds += $samples / $rate;
            }
            $pos += $size;
        }

        return [$frames, $seconds];
    }
}
