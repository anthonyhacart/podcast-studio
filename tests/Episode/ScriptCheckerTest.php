<?php

declare(strict_types=1);

namespace App\Tests\Episode;

use App\Episode\Line;
use App\Episode\Script;
use App\Episode\ScriptChecker;
use PHPUnit\Framework\TestCase;

final class ScriptCheckerTest extends TestCase
{
    private ScriptChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new ScriptChecker(Fixtures::show());
    }

    public function testAcceptsAValidScript(): void
    {
        self::assertSame([], $this->checker->check(Fixtures::script(), Fixtures::brief()));
    }

    public function testRejectsUnknownHostEmotionAndSource(): void
    {
        $script = Fixtures::script();
        $script = new Script($script->title, [
            new Line('bob', 'neutral', 'Hello there.', []),
            new Line('jane', 'excited', 'Excited Jane does not exist.', []),
            new Line('paul', 'neutral', 'Source three does not exist.', [3]),
            ...$script->lines,
        ]);

        $problems = $this->checker->check($script, Fixtures::brief());

        self::assertCount(3, $problems);
        self::assertStringContainsString('unknown host "bob"', $problems[0]);
        self::assertStringContainsString('Jane has no emotion "excited"', $problems[1]);
        self::assertStringContainsString('cites source 3', $problems[2]);
    }

    public function testRequiresAtLeastOneCitedSource(): void
    {
        $script = Fixtures::script();
        $lines = array_map(static fn (Line $line) => new Line($line->host, $line->emotion, $line->text, []), $script->lines);

        self::assertSame(['No line cites a source: every fact must come from the sources.'], $this->checker->check(new Script('Episode', $lines), Fixtures::brief()));
    }

    public function testRejectsAScriptFarFromTheRequestedLength(): void
    {
        self::assertSame(['The script has 50 words, aim for about 150.'], $this->checker->check(Fixtures::script(50), Fixtures::brief()));
        self::assertSame(['The script has 500 words, aim for about 300.'], $this->checker->check(Fixtures::script(500), Fixtures::brief(2)));
    }

    public function testRejectsRelativeDays(): void
    {
        $script = Fixtures::script();
        $script = new Script($script->title, [new Line('paul', 'neutral', 'Gen.G play Tomorrow, then Karmine on the sixth.', []), ...$script->lines]);

        self::assertSame(['Line 1 says "Tomorrow": give the date itself, people listen later.'], $this->checker->check($script, Fixtures::brief()));
    }

    public function testRejectsEmptyAndOverlongLines(): void
    {
        $script = Fixtures::script();
        $script = new Script($script->title, [new Line('paul', 'neutral', ' ', []), new Line('jane', 'neutral', str_repeat('a', 401), []), ...$script->lines]);

        $problems = $this->checker->check($script, Fixtures::brief());

        self::assertStringContainsString('Line 1 is empty', $problems[0]);
        self::assertStringContainsString('Line 2 is too long', $problems[1]);
    }
}
