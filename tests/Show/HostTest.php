<?php

declare(strict_types=1);

namespace App\Tests\Show;

use App\Tests\Episode\Fixtures;
use PHPUnit\Framework\TestCase;

final class HostTest extends TestCase
{
    public function testFallsBackToTheNeutralVoice(): void
    {
        $paul = Fixtures::show()->hosts['paul'];

        self::assertSame('en_paul_excited', $paul->voiceFor('excited'));
        self::assertSame('en_paul_neutral', $paul->voiceFor('sarcasm'));
    }
}
