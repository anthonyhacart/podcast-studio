<?php

declare(strict_types=1);

namespace App\Tests\Show;

use App\Tests\Episode\Fixtures;
use PHPUnit\Framework\TestCase;

final class ShowTest extends TestCase
{
    public function testPronouncesWholeWordsOnly(): void
    {
        $show = Fixtures::show();

        self::assertSame('T One faces Gen G, then T One again.', $show->pronounce('T1 faces Gen.G, then T1 again.'));
        self::assertSame("T One's run", $show->pronounce("T1's run"));
        self::assertSame('ST1 and T10 stay as they are', $show->pronounce('ST1 and T10 stay as they are'));
    }

    public function testFallsBackToTheNeutralVoice(): void
    {
        $paul = Fixtures::show()->hosts['paul'];

        self::assertSame('en_paul_excited', $paul->voiceFor('excited'));
        self::assertSame('en_paul_neutral', $paul->voiceFor('sarcasm'));
    }
}
