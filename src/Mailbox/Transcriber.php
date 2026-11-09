<?php

declare(strict_types=1);

namespace App\Mailbox;

use Symfony\AI\Platform\Bridge\Mistral\SpeechToText;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The hosts' ears: Voxtral turns a recorded question into text. Any format a browser
 * records (webm, mp4, mp3) works, with or without a file extension.
 */
final readonly class Transcriber
{
    /** @param non-empty-string $model */
    public function __construct(
        private PlatformInterface $platform,
        #[Autowire(env: 'TRANSCRIPTION_MODEL')] private string $model,
    ) {
    }

    public function transcribe(string $path): string
    {
        // the bridge's catalog does not list the transcription models: name the model class
        return trim($this->platform->invoke(new SpeechToText($this->model), Audio::fromFile($path))->asText());
    }
}
