<?php

declare(strict_types=1);

namespace App\Episode;

use RuntimeException;
use Throwable;

/**
 * The voice service refused a line, e.g. its moderation flagged it: revising the line fixes it.
 */
final class UnspeakableLineException extends RuntimeException
{
    public function __construct(int $line, string $text, Throwable $previous)
    {
        parent::__construct(\sprintf('Line %d could not be voiced ("%s"): %s Revise this line, then publish again.', $line, $text, $previous->getMessage()), previous: $previous);
    }
}
