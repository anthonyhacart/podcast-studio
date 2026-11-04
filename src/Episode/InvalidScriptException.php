<?php

declare(strict_types=1);

namespace App\Episode;

use RuntimeException;

final class InvalidScriptException extends RuntimeException
{
    /**
     * @param list<string> $problems
     */
    public function __construct(
        public readonly Script $script,
        public readonly array $problems,
    ) {
        parent::__construct("The writer could not produce a valid script:\n- ".implode("\n- ", $problems));
    }
}
