<?php

declare(strict_types=1);

namespace App\Tests\Episode;

use App\Episode\Chapter;
use PHPUnit\Framework\TestCase;

final class ChapterTest extends TestCase
{
    public function testStartsEachChapterWhenItsFirstLineStarts(): void
    {
        $timings = array_map(static fn (int $i) => [$i * 12.5, $i * 12.5 + 12.0], range(0, 9));

        $chapters = Chapter::fromScript(Fixtures::script(), $timings);

        self::assertEquals([new Chapter('The format', 0.0), new Chapter('The favourites', 62.5)], $chapters);
        self::assertSame('1:03', $chapters[1]->at());
    }
}
