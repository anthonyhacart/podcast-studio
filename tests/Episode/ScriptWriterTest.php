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

final class ScriptWriterTest extends TestCase
{
    /** @var list<MessageBag> */
    private array $calls = [];

    public function testReturnsAValidScriptAtOnce(): void
    {
        $script = $this->writer(Fixtures::script())->write(Fixtures::brief());

        self::assertCount(1, $this->calls);
        self::assertSame('Episode', $script->title);
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

    private function writer(Script ...$scripts): ScriptWriter
    {
        $agent = self::createStub(AgentInterface::class);
        $agent->method('call')->willReturnCallback(function (MessageBag $messages) use (&$scripts): ExecutionInterface {
            $this->calls[] = clone $messages;
            $execution = $this->createStub(ExecutionInterface::class);
            $execution->method('asObject')->willReturn(array_shift($scripts));

            return $execution;
        });

        return new ScriptWriter($agent, new ScriptChecker(Fixtures::show()), Fixtures::show());
    }

    private function lastUserText(): string
    {
        $bag = end($this->calls);
        self::assertInstanceOf(MessageBag::class, $bag);
        $last = $bag->latestAs(Role::User);

        return $last->asText() ?? '';
    }
}
