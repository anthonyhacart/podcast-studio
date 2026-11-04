<?php

declare(strict_types=1);

namespace App\Episode;

use RuntimeException;
use Throwable;

/**
 * The voice service refused a line, e.g. its moderation flagged it: rephrasing the line fixes it.
 */
final class UnspeakableLineException extends RuntimeException
{
    public function __construct(
        public readonly int $lineNumber,
        public readonly string $text,
        Throwable $previous,
    ) {
        parent::__construct(\sprintf('Line %d could not be voiced ("%s"): %s', $lineNumber, $text, $previous->getMessage()), previous: $previous);
    }
}
