<?php

declare(strict_types=1);

namespace App\Mailbox;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * The form sent with a recorded question.
 */
final readonly class AskQuestion
{
    public function __construct(
        #[Assert\Length(max: 40)]
        public ?string $name = null,
    ) {
    }
}
