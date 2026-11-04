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

        [, $one] = $joiner->join([$clip]);
        [$mp3, $three] = $joiner->join([$clip, $clip]);

        // two clips and one pause, each the length of the pause file
        self::assertEqualsWithDelta(3 * $one, $three, 0.001);
        self::assertSame(0xFFE0, ((\ord($mp3[0]) << 8) | \ord($mp3[1])) & 0xFFE0, 'starts with an MPEG frame');
    }

    public function testDropsTheId3TagAndTheXingHeader(): void
    {
        $joiner = new Mp3Joiner(new Filesystem(), self::PAUSE);
        [$plain] = $joiner->join([(string) file_get_contents(self::PAUSE)]);

        // an ID3v2 tag (10-byte header, 4-byte payload) and an empty Xing frame in front of the same audio
        // a real Xing tag sits right after the side info (9 bytes for MPEG 2 mono)
        $xing = str_pad(substr($plain, 0, 4).str_repeat("\0", 9).'Xing', \strlen($this->firstFrame($plain)), "\0");
        [$joined, $seconds] = $joiner->join(["ID3\x04\x00\x00\x00\x00\x00\x04TAG!".$xing.$plain]);

        self::assertSame($plain, $joined);
        self::assertGreaterThan(0.3, $seconds);
    }

    private function firstFrame(string $mp3): string
    {
        // 32 kbps, 22,050 Hz, MPEG 2 layer III: 72 * 32000 / 22050 = 104 bytes (+1 when padded)
        return substr($mp3, 0, 104 + ((\ord($mp3[2]) >> 1) & 1));
    }
}
