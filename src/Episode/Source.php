<?php

declare(strict_types=1);

namespace App\Episode;

/**
 * A source brought by the client: what it read, not a page for the server to fetch.
 */
final readonly class Source
{
    public function __construct(
        public string $title,
        public string $notes,
        public ?string $url = null,
    ) {
    }
}
