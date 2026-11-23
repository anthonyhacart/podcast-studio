<?php

declare(strict_types=1);

namespace App\Speech;

use SensitiveParameter;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Posts {model, input, voice, response_format, ...} to an OpenAI-compatible speech endpoint.
 *
 * Temporary copy of the generic bridge's TextToSpeech\ModelClient (symfony/ai#3058), see SpeechModel.
 */
final readonly class SpeechClient implements ModelClientInterface
{
    private string $baseUrl;

    public function __construct(
        private HttpClientInterface $httpClient,
        string $baseUrl,
        #[SensitiveParameter] private ?string $apiKey = null,
        private string $path = '/v1/audio/speech',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof SpeechModel;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (isset($options['stream_format']) || isset($options['stream'])) {
            throw new InvalidArgumentException('Streaming text to speech results is not supported yet.');
        }

        $input = \is_string($payload) ? $payload : ($payload['text'] ?? throw new InvalidArgumentException('The payload must contain a "text" key.'));

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.$this->path, [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'application/json'],
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'input' => $input,
            ]),
        ]));
    }
}
