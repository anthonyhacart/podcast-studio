<?php

declare(strict_types=1);

namespace App\Tests\Show;

use App\Tests\Episode\Fixtures;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class ShowTest extends TestCase
{
    public function testAnEpisodeHasTheUsualDuoUnlessItsEditorChooses(): void
    {
        self::assertSame(['paul', 'jane'], array_keys(Fixtures::show()->cast()));
    }

    public function testAnEditorChoosesTheTwoHosts(): void
    {
        $cast = Fixtures::show()->cast(['Oliver', 'jane']);

        self::assertSame(['oliver', 'jane'], array_keys($cast));
        self::assertSame('Oliver', $cast['oliver']->name);
    }

    public function testRefusesSomeoneWhoIsNotAHostOfTheShow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"bob" is not a host of the show (hosts: paul, jane, oliver).');

        Fixtures::show()->cast(['paul', 'bob']);
    }

    /**
     * @param list<string> $hosts
     */
    #[TestWith([['paul']])]
    #[TestWith([['paul', 'paul']])]
    #[TestWith([['paul', 'jane', 'oliver']])]
    public function testAnEpisodeHasTwoHosts(array $hosts): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An episode has two hosts, choose two among paul, jane, oliver.');

        Fixtures::show()->cast($hosts);
    }
}
