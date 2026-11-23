<?php

declare(strict_types=1);

namespace App\Speech;

use Symfony\AI\Platform\Bridge\Generic\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Builds the "app.platform.voicestudio" platform: one voice model on an OpenAI-compatible speech server.
 *
 * Temporary, see SpeechModel: the generic bridge's Factory does the same once symfony/ai#3058 is released.
 */
final class SpeechPlatformFactory
{
    /**
     * @param non-empty-string $model
     */
    public static function create(HttpClientInterface $httpClient, string $baseUrl, string $model, ?string $apiKey = null): Platform
    {
        $catalog = new ModelCatalog([
            $model => [
                'class' => SpeechModel::class,
                'capabilities' => [Capability::INPUT_TEXT, Capability::OUTPUT_AUDIO, Capability::TEXT_TO_SPEECH],
            ],
        ]);

        return new Platform([
            new Provider('voicestudio', [new SpeechClient($httpClient, $baseUrl, '' !== $apiKey ? $apiKey : null)], [new SpeechResultConverter()], $catalog),
        ]);
    }
}
