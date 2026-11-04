<?php

declare(strict_types=1);

namespace App\Show;

final readonly class Host
{
    /**
     * @param array<string, string> $voices emotion => Voxtral voice id
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $persona,
        public array $voices,
    ) {
    }

    public function voiceFor(string $emotion): string
    {
        return $this->voices[$emotion] ?? $this->voices['neutral'];
    }
}
