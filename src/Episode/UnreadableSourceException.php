<?php

declare(strict_types=1);

namespace App\Episode;

use RuntimeException;
use Throwable;

/**
 * A page given as a source could not be read: the editor can drop the link or paste its text as notes.
 */
final class UnreadableSourceException extends RuntimeException
{
    public function __construct(string $url, string $reason, ?Throwable $previous = null)
    {
        parent::__construct(\sprintf('Could not read %s: %s.', $url, $reason), previous: $previous);
    }
}
