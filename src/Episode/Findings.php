<?php

declare(strict_types=1);

namespace App\Episode;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * What the "researcher" agent answers: the pages it picked among its search results.
 */
final readonly class Findings
{
    /**
     * @param list<string> $urls
     */
    public function __construct(
        #[Schema(description: 'URLs of the pages picked, exactly as the search returned them, the most useful first')]
        public array $urls,
    ) {
    }
}
