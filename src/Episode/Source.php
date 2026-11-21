<?php

declare(strict_types=1);

namespace App\Episode;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * A source brought by the editor: what the MCP client read, or the text of a page
 * given as a link in the browser (SourceReader).
 */
final readonly class Source
{
    public function __construct(
        #[Assert\NotBlank]
        public string $title,
        #[Assert\NotBlank]
        public string $notes,
        // shown as a link next to the script and on the episode's page
        #[Assert\Url(message: '{{ value }} is not a link to a web page.', protocols: ['http', 'https'], requireTld: true)]
        public ?string $url = null,
    ) {
    }
}
