<?php

declare(strict_types=1);

namespace App\Tests\Speech;

use App\Speech\SpeechResultConverter;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SpeechResultConverterTest extends TestCase
{
    #[TestWith(['audio/mpeg', 'audio/mpeg'])]
    #[TestWith(['Audio/OGG; codecs=opus', 'audio/ogg'])]
    public function testTheAudioBodyIsTheResult(string $contentType, string $mimeType): void
    {
        $result = new SpeechResultConverter()->convert($this->raw(new MockResponse('audio', ['response_headers' => ['content-type' => $contentType]])));

        self::assertSame('audio', $result->getContent());
        self::assertSame($mimeType, $result->getMimeType());
    }

    public function testAJsonBodyIsNotAudio(): void
    {
        $this->expectException(RuntimeException::class);

        new SpeechResultConverter()->convert($this->raw(new MockResponse('{}', ['response_headers' => ['content-type' => 'application/json']])));
    }

    private function raw(MockResponse $response): RawHttpResult
    {
        return new RawHttpResult(new MockHttpClient($response)->request('POST', 'http://127.0.0.1:3900/v1/audio/speech'));
    }
}
