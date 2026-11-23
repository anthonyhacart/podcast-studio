<?php

declare(strict_types=1);

namespace App\Speech;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Turns the raw audio body of the speech endpoint into a BinaryResult, with the MIME type of its Content-Type.
 *
 * Temporary copy of the generic bridge's TextToSpeech\ResultConverter (symfony/ai#3058), see SpeechModel.
 */
final class SpeechResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof SpeechModel;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function convert(RawResultInterface $result, array $options = []): BinaryResult
    {
        $response = $result->getObject();
        \assert($response instanceof ResponseInterface);

        $this->throwOnHttpError($response);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $response->getStatusCode(), $response->getContent(false)));
        }

        $contentType = $response->getHeaders(false)['content-type'][0] ?? null;
        $mimeType = null !== $contentType ? strtolower(trim(explode(';', $contentType)[0])) : null;

        if (null !== $mimeType && (str_starts_with($mimeType, 'application/json') || str_starts_with($mimeType, 'text/'))) {
            throw new RuntimeException(\sprintf('Expected an audio response, got "%s": "%s"', $mimeType, $response->getContent(false)));
        }

        return new BinaryResult($response->getContent(), '' !== $mimeType ? $mimeType : null);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
