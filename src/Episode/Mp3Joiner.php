<?php

declare(strict_types=1);

namespace App\Episode;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Joins MP3 clips by concatenating their MPEG frames, with a short pause between
 * clips, behind a header frame that tells players the real length; no ffmpeg needed.
 * Clips must share a sample rate (Voxtral: 22,050 Hz mono).
 */
final readonly class Mp3Joiner
{
    private const SAMPLE_RATES = [3 => [44100, 48000, 32000], 2 => [22050, 24000, 16000], 0 => [11025, 12000, 8000]];
    private const BITRATES_V1 = [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320];
    private const BITRATES_V2 = [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160];
    // 64 kbps for MPEG 2 (112 for MPEG 1): a frame of 208 bytes or more
    private const XING_BITRATE_INDEX = 8;

    public function __construct(
        private Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/resources/audio/pause.mp3')] private string $pausePath,
    ) {
    }

    /**
     * @param list<string> $clips MP3 file contents, in order
     *
     * @return array{string, list<array{float, float}>} the joined MP3, and when each clip starts and ends, in seconds
     */
    public function join(array $clips): array
    {
        [$pause, $pauseSeconds, $pauseSizes] = $this->frames($this->filesystem->readFile($this->pausePath));
        $mp3 = '';
        $sizes = [];
        $timings = [];
        $seconds = 0.0;
        foreach ($clips as $i => $clip) {
            [$frames, $duration, $clipSizes] = $this->frames($clip);
            if ($i > 0) {
                $mp3 .= $pause;
                $sizes = [...$sizes, ...$pauseSizes];
                $seconds += $pauseSeconds;
            }
            $mp3 .= $frames;
            $sizes = [...$sizes, ...$clipSizes];
            $timings[] = [$seconds, $seconds += $duration];
        }

        return [$this->xingFrame($mp3, $sizes).$mp3, $timings];
    }

    /**
     * Strips the ID3v2 tag and the Xing/Info header frame (it would carry the
     * clip's own length), keeps the audio frames and adds up their duration.
     *
     * @return array{string, float, list<int>} the frames, their duration in seconds, the size of each in bytes
     */
    private function frames(string $mp3): array
    {
        if (str_starts_with($mp3, 'ID3')) {
            $mp3 = substr($mp3, 10 + ((\ord($mp3[6]) << 21) | (\ord($mp3[7]) << 14) | (\ord($mp3[8]) << 7) | \ord($mp3[9])));
        }

        $frames = '';
        $seconds = 0.0;
        $sizes = [];
        for ($pos = 0, $length = \strlen($mp3); $pos + 4 <= $length;) {
            $header = (\ord($mp3[$pos]) << 24) | (\ord($mp3[$pos + 1]) << 16) | (\ord($mp3[$pos + 2]) << 8) | \ord($mp3[$pos + 3]);
            if (0xFFE00000 !== ($header & 0xFFE00000)) {
                ++$pos;
                continue;
            }

            $version = ($header >> 19) & 3;
            $rate = self::SAMPLE_RATES[$version][($header >> 10) & 3] ?? throw new InvalidArgumentException("Unsupported MP3 frame at byte {$pos}.");
            $size = self::frameSize($version, ($header >> 12) & 15, $rate) + (($header >> 9) & 1);
            if ($size <= 4) {
                ++$pos;
                continue;
            }

            $frame = substr($mp3, $pos, $size);
            if (!str_contains(substr($frame, 0, 48), 'Xing') && !str_contains(substr($frame, 0, 48), 'Info')) {
                $frames .= $frame;
                $seconds += self::samples($version) / $rate;
                $sizes[] = \strlen($frame);
            }
            $pos += $size;
        }

        return [$frames, $seconds, $sizes];
    }

    /**
     * The header frame of a file whose frames have different bitrates, as the voices'
     * have: without it a player guesses the length from the bitrate of the first frame
     * (a browser showed 6:10 for an episode of 1:58). It gives the number of frames,
     * the size of the file and a seek table; players do not play it.
     *
     * @param list<int> $sizes the size of each frame of $audio, in bytes
     */
    private function xingFrame(string $audio, array $sizes): string
    {
        if ([] === $sizes) {
            return '';
        }

        // same version, sample rate and channels as the audio, at a bitrate that makes room for the tag
        $header = (\ord($audio[0]) << 24) | (\ord($audio[1]) << 16) | (\ord($audio[2]) << 8) | \ord($audio[3]);
        $header = ($header & ~0xF200) | (self::XING_BITRATE_INDEX << 12) | 0x10000;
        $version = ($header >> 19) & 3;
        $mono = 3 === (($header >> 6) & 3);
        $size = self::frameSize($version, self::XING_BITRATE_INDEX, self::SAMPLE_RATES[$version][($header >> 10) & 3]);
        // the tag sits after the side information of the frame
        $sideInformation = 3 === $version ? ($mono ? 17 : 32) : ($mono ? 9 : 17);
        $bytes = $size + \strlen($audio);

        // where the frame at each percent of the duration starts, as a fraction of the file out of 256
        $offsets = [];
        $offset = $size;
        foreach ($sizes as $frameSize) {
            $offsets[] = $offset;
            $offset += $frameSize;
        }
        $seekTable = [];
        for ($percent = 0; $percent < 100; ++$percent) {
            $seekTable[] = min(255, intdiv(256 * $offsets[intdiv($percent * \count($sizes), 100)], $bytes));
        }

        // flags: the tag has the frame count, the byte count and the seek table
        return str_pad(pack('N', $header).str_repeat("\0", $sideInformation).'Xing'.pack('N3C100', 7, \count($sizes), $bytes, ...$seekTable), $size, "\0");
    }

    private static function frameSize(int $version, int $bitrateIndex, int $rate): int
    {
        $bitrate = 1000 * (3 === $version ? self::BITRATES_V1 : self::BITRATES_V2)[$bitrateIndex];

        return intdiv(intdiv(self::samples($version), 8) * $bitrate, $rate);
    }

    /**
     * Samples in a layer III frame: 1,152 for MPEG 1, 576 for MPEG 2 and 2.5.
     */
    private static function samples(int $version): int
    {
        return 3 === $version ? 1152 : 576;
    }
}
