<?php

declare(strict_types=1);

namespace App\Tests\Episode;

use App\Episode\InvalidScriptException;
use App\Episode\Script;
use App\Episode\ScriptChecker;
use App\Episode\ScriptWriter;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\ExecutionInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\Component\Clock\MockClock;

final class ScriptWriterTest extends TestCase
{
    /** @var list<MessageBag> */
    private array $calls = [];

    public function testReturnsAValidScriptAtOnce(): void
    {
        $script = $this->writer(Fixtures::script())->write(Fixtures::brief());

        self::assertCount(1, $this->calls);
        self::assertSame('Episode', $script->title);
        self::assertStringContainsString('Today: Wednesday 4 November 2026.', $this->lastUserText());
        self::assertStringContainsString('[2] Teams', $this->lastUserText());
    }

    public function testAsksAgainWithTheProblemsFound(): void
    {
        $script = $this->writer(Fixtures::script(20), Fixtures::script())->write(Fixtures::brief());

        self::assertCount(2, $this->calls);
        self::assertCount(10, $script->lines);
        self::assertStringContainsString("Problems found in your script:\n- The script has 20 words", $this->lastUserText());
    }

    public function testGivesUpAfterTheSecondInvalidScript(): void
    {
        $this->expectException(InvalidScriptException::class);
        $this->expectExceptionMessage('The script has 20 words');

        $this->writer(Fixtures::script(20), Fixtures::script(20))->write(Fixtures::brief());
    }

    public function testRevisesWithTheEditorsInstructions(): void
    {
        $this->writer(Fixtures::script())->revise(Fixtures::brief(), Fixtures::script(), 'Shorter, please.');

        self::assertStringContainsString("The editor asks for these changes, apply them and return the whole script:\nShorter, please.", $this->lastUserText());
        self::assertCount(3, $this->calls[0], 'brief, previous script, instructions');
    }

    private function writer(Script ...$scripts): ScriptWriter
    {
        $agent = self::createStub(AgentInterface::class);
        $agent->method('call')->willReturnCallback(function (MessageBag $messages) use (&$scripts): ExecutionInterface {
            $this->calls[] = clone $messages;
            $execution = self::createStub(ExecutionInterface::class);
            $execution->method('asObject')->willReturn(array_shift($scripts));

            return $execution;
        });

        return new ScriptWriter($agent, new ScriptChecker(Fixtures::show()), Fixtures::show(), new MockClock('2026-11-04 10:00'));
    }

    private function lastUserText(): string
    {
        $bag = end($this->calls);
        self::assertInstanceOf(MessageBag::class, $bag);

        return $bag->latestAs(Role::User)->asText() ?? '';
    }
}
