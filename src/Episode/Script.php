<?php

declare(strict_types=1);

namespace App\Episode;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

final readonly class Script
{
    /**
     * @param list<Line> $lines
     */
    public function __construct(
        public string $title,
        public array $lines,
        #[Schema(description: 'Two sentences for the show notes: what the episode covers, only facts from the sources')]
        public string $summary = '',
    ) {
    }
}
