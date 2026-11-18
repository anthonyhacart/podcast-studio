<?php

declare(strict_types=1);

namespace App\Episode;

use RuntimeException;

/**
 * The researcher found no page it could read on the topic: the editor can bring links or notes instead.
 */
final class NothingFoundException extends RuntimeException
{
    public function __construct(string $topic)
    {
        parent::__construct(\sprintf('The hosts found nothing they could read about "%s". Give them a link or some notes.', $topic));
    }
}
