<?php

declare(strict_types=1);

namespace App\Tests\Episode;

use App\Episode\Brief;
use App\Episode\Line;
use App\Episode\Script;
use App\Episode\Source;
use App\Show\Show;

final class Fixtures
{
    public const PAUSE = __DIR__.'/../../resources/audio/pause.mp3';

    public static function show(): Show
    {
        return new Show([
            'title' => 'Test Show',
            'description' => 'A show for tests.',
            'author' => 'Tests',
            'language' => 'en',
            'hosts' => [
                'paul' => ['name' => 'Paul', 'persona' => 'The enthusiast.', 'voices' => ['neutral' => 'en_paul_neutral', 'excited' => 'en_paul_excited']],
                'jane' => ['name' => 'Jane', 'persona' => 'The analyst.', 'voices' => ['neutral' => 'gb_jane_neutral', 'sarcasm' => 'gb_jane_sarcasm']],
            ],
        ]);
    }

    public static function brief(int $minutes = 1): Brief
    {
        return new Brief('Worlds 2026', [
            new Source('Format', 'Final on 14 November at Barclays Center.'),
            new Source('Teams', 'T1 are the three-time defending champions.'),
        ], $minutes);
    }

    /**
     * A script of about $words words, the first line citing source 1.
     */
    public static function script(int $words = 150): Script
    {
        $lines = [];
        for ($i = 0; $i < 10; ++$i) {
            $lines[] = new Line(0 === $i % 2 ? 'paul' : 'jane', 'neutral', trim(str_repeat('word ', intdiv($words, 10))), 0 === $i ? [1] : []);
        }

        return new Script('Episode', $lines);
    }
}
