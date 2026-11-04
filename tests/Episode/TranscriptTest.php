<?php

declare(strict_types=1);

namespace App\Tests\Episode;

use App\Episode\Line;
use App\Episode\Script;
use App\Episode\Transcript;
use PHPUnit\Framework\TestCase;

final class TranscriptTest extends TestCase
{
    public function testOneCuePerLineWithTheHostsName(): void
    {
        $script = new Script('Episode', [
            new Line('paul', 'excited', 'Eight teams left!', [1]),
            new Line('jane', 'sarcasm', "Calm down.\n\nIt --> ends", []),
        ]);

        self::assertSame(<<<'VTT'
            WEBVTT

            1
            00:00:00.000 --> 00:00:02.490
            <v Paul>Eight teams left!

            2
            00:00:02.908 --> 01:02:05.250
            <v Jane>Calm down.  It -> ends

            VTT, (new Transcript(Fixtures::show()))->vtt($script, [[0.0, 2.49], [2.9075, 3725.25]]));
    }
}
