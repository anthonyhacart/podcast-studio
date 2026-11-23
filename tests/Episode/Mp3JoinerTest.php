<?php

declare(strict_types=1);

namespace App\Tests\Episode;

use App\Episode\Mp3Joiner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class Mp3JoinerTest extends TestCase
{
    private const PAUSE = __DIR__.'/../../resources/audio/pause.mp3';

    public function testJoinsClipsWithAPauseBetweenThem(): void
    {
        $joiner = new Mp3Joiner(new Filesystem(), self::PAUSE);
        $clip = (string) file_get_contents(self::PAUSE);

        [, [[, $one]]] = $joiner->join([$clip]);
        [$mp3, $timings] = $joiner->join([$clip, $clip]);

        // two clips and one pause, each the length of the pause file
        self::assertEqualsWithDelta(0.0, $timings[0][0], 0.001);
        self::assertEqualsWithDelta($one, $timings[0][1], 0.001);
        self::assertEqualsWithDelta(2 * $one, $timings[1][0], 0.001);
        self::assertEqualsWithDelta(3 * $one, $timings[1][1], 0.001);
        self::assertSame(0xFFE0, ((\ord($mp3[0]) << 8) | \ord($mp3[1])) & 0xFFE0, 'starts with an MPEG frame');
    }

    public function testDropsTheId3TagAndTheXingHeaderOfAClip(): void
    {
        $joiner = new Mp3Joiner(new Filesystem(), self::PAUSE);
        $clip = (string) file_get_contents(self::PAUSE);
        [$plain] = $joiner->join([$clip]);

        // an ID3v2 tag (10-byte header, 4-byte payload) and an empty Xing frame in front of the same audio
        // a real Xing tag sits right after the side info (9 bytes for MPEG 2 mono)
        $xing = str_pad(substr($clip, 0, 4).str_repeat("\0", 9).'Xing', \strlen($this->firstFrame($clip)), "\0");
        [$joined, [[, $seconds]]] = $joiner->join(["ID3\x04\x00\x00\x00\x00\x00\x04TAG!".$xing.$clip]);

        self::assertSame($plain, $joined);
        self::assertGreaterThan(0.3, $seconds);
    }

    public function testTellsPlayersTheRealLengthInAHeaderFrame(): void
    {
        $joiner = new Mp3Joiner(new Filesystem(), self::PAUSE);
        $clip = (string) file_get_contents(self::PAUSE);
        [$mp3, $timings] = $joiner->join([$clip, $clip]);

        // 64 kbps, 22,050 Hz, MPEG 2 layer III mono, no padding: a frame of 208 bytes, the tag after 9 bytes of side info
        self::assertSame("\xFF\xF3\x80\xC4", substr($mp3, 0, 4));
        self::assertSame('Xing', substr($mp3, 13, 4));
        ['flags' => $flags, 'frames' => $frames, 'bytes' => $bytes] = (array) unpack('Nflags/Nframes/Nbytes', $mp3, 17);
        self::assertSame(7, $flags, 'frame count, byte count and seek table');
        self::assertIsInt($frames);
        self::assertSame(\strlen($mp3), $bytes);
        // a frame is 576 samples at 22,050 Hz: the frame count gives the length the timings give
        self::assertEqualsWithDelta($timings[1][1], $frames * 576 / 22050, 0.001);
        // the seek table: where each percent of the length starts, out of 256, from the first audio frame to near the end
        $seekTable = array_map(\ord(...), str_split(substr($mp3, 29, 100)));
        self::assertSame(intdiv(256 * 208, \strlen($mp3)), $seekTable[0]);
        self::assertGreaterThan(128, $seekTable[99]);
        $sorted = $seekTable;
        sort($sorted);
        self::assertSame($sorted, $seekTable);
        self::assertSame(0xFFE0, ((\ord($mp3[208]) << 8) | \ord($mp3[209])) & 0xFFE0, 'the audio follows');
    }

    public function testClipsAt24kHzGetThePauseAt24kHz(): void
    {
        // VoiceStudio's MP3s are 24,000 Hz: a 22,050 Hz pause between them would not play right
        $joiner = new Mp3Joiner(new Filesystem(), self::PAUSE);
        $clip = (string) file_get_contents(\dirname(self::PAUSE).'/pause-24000.mp3');

        [$mp3, $timings] = $joiner->join([$clip, $clip]);

        // MPEG 2, 24,000 Hz: sample rate index 1 in every frame, the header frame included
        self::assertSame(1, (\ord($mp3[2]) >> 2) & 3);
        self::assertEqualsWithDelta(3 * $timings[0][1], $timings[1][1], 0.001, 'the pause is as long as a clip');
    }

    public function testJoiningNothingGivesNothing(): void
    {
        self::assertSame(['', []], new Mp3Joiner(new Filesystem(), self::PAUSE)->join([]));
    }

    private function firstFrame(string $mp3): string
    {
        // 32 kbps, 22,050 Hz, MPEG 2 layer III: 72 * 32000 / 22050 = 104 bytes (+1 when padded)
        return substr($mp3, 0, 104 + ((\ord($mp3[2]) >> 1) & 1));
    }
}
