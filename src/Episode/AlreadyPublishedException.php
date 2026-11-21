<?php

declare(strict_types=1);

namespace App\Episode;

use RuntimeException;

/**
 * The episode is out: its audio is in the feed, its script can no longer change.
 */
final class AlreadyPublishedException extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf('Episode "%s" is already published. Draft a new episode instead.', $id));
    }
}
