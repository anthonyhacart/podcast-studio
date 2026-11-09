<?php

declare(strict_types=1);

namespace App\Mailbox;

use DateTimeImmutable;

/**
 * A question a listener asked the hosts, by voice, transcribed.
 */
final readonly class Question
{
    public function __construct(
        public string $id,
        public string $text,
        public DateTimeImmutable $askedAt,
        public ?string $name = null,
    ) {
    }
}
