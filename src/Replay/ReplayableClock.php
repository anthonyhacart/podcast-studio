<?php

declare(strict_types=1);

namespace App\Replay;

use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * The clock of the app. With CASSETTE set, it stops on the day the cassettes are
 * recorded: requests carry the date ("Today: …", when an episode was published),
 * and a replay must send the very requests the recording sent.
 */
#[AsDecorator('clock')]
final readonly class ReplayableClock implements ClockInterface
{
    public const RECORDED_AT = '2026-11-04 10:00:00';

    private ClockInterface $clock;

    public function __construct(
        #[AutowireDecorated] ClockInterface $clock,
        #[Autowire(env: 'default::CASSETTE')] ?string $cassette = null,
    ) {
        $this->clock = null === $cassette || '' === $cassette ? $clock : new MockClock(self::RECORDED_AT);
    }

    public function now(): DateTimeImmutable
    {
        return $this->clock->now();
    }

    public function sleep(float|int $seconds): void
    {
        $this->clock->sleep($seconds);
    }

    public function withTimeZone(DateTimeZone|string $timezone): static
    {
        return new static($this->clock->withTimeZone($timezone));
    }
}
