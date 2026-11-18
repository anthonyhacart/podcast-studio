<?php

declare(strict_types=1);

namespace App\Episode;

/**
 * A source brought by the editor: what the MCP client read, or the text of a page
 * given as a link in the browser (SourceReader).
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
