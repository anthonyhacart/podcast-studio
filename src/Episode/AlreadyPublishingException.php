<?php

declare(strict_types=1);

namespace App\Episode;

use RuntimeException;

/**
 * The episode is being voiced right now: publishing it a second time would pay for the voices twice.
 */
final class AlreadyPublishingException extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf('Episode "%s" is already being published, wait for it to finish.', $id));
    }
}
