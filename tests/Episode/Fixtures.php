<?php

declare(strict_types=1);

namespace App\Tests\Episode;

use App\Episode\Brief;
use App\Episode\EpisodeRepository;
use App\Episode\Line;
use App\Episode\Script;
use App\Episode\Source;
use App\Show\Show;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

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

    /**
     * A repository in $dir, its clock on 4 November 2026 at 10:00.
     */
    public static function episodes(string $dir): EpisodeRepository
    {
        $serializer = new Serializer([new ArrayDenormalizer(), new DateTimeNormalizer(), new ObjectNormalizer(propertyTypeExtractor: new PhpStanExtractor())], [new JsonEncoder()]);

        return new EpisodeRepository(new Filesystem(), $serializer, new MockClock('2026-11-04 10:00:00'), $dir);
    }

    public static function brief(int $minutes = 1): Brief
    {
        return new Brief('Worlds 2026', [
            new Source('Format', 'Final on 14 November at Barclays Center.'),
            new Source('Teams', 'T1 are the three-time defending champions.'),
        ], $minutes);
    }

    /**
     * A script of about $words words in two chapters, the first line citing source 1.
     */
    public static function script(int $words = 150): Script
    {
        $lines = [];
        for ($i = 0; $i < 10; ++$i) {
            $lines[] = new Line(0 === $i % 2 ? 'paul' : 'jane', 'neutral', trim(str_repeat('word ', intdiv($words, 10))), 0 === $i ? [1] : [], match ($i) {
                0 => 'The format',
                5 => 'The favourites',
                default => null,
            });
        }

        return new Script('Episode', $lines, 'Paul and Jane on the knockout stage.');
    }
}
