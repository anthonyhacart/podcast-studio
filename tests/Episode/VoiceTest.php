<?php

declare(strict_types=1);

namespace App\Tests\Episode;

use App\Episode\Line;
use App\Episode\Script;
use App\Episode\UnspeakableLineException;
use App\Episode\Voice;
use App\Speech\SpeechPlatformFactory;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class VoiceTest extends TestCase
{
    public function testVoiceStudioSpeaksEachLineWithTheLocalVoiceOfItsHost(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            self::assertIsString($options['body']);
            $requests[] = [$url, json_decode($options['body'], true)];

            return new MockResponse('mp3-'.\count($requests), ['response_headers' => ['content-type' => 'audio/mpeg']]);
        });

        $clips = $this->voice(SpeechPlatformFactory::create($httpClient, 'http://127.0.0.1:3900/', 'kittentts'))->speak(new Script('Episode', [
            new Line('paul', 'excited', 'Welcome!', []),
            new Line('jane', 'sarcasm', 'Oh, joy.', []),
            new Line('oliver', 'neutral', 'Once upon a time.', []),
        ]));

        self::assertSame(['mp3-1', 'mp3-2', 'mp3-3'], $clips);
        self::assertSame([
            ['http://127.0.0.1:3900/v1/audio/speech', ['voice' => 'expr-voice-2-m', 'response_format' => 'mp3', 'model' => 'kittentts', 'input' => 'Welcome!']],
            ['http://127.0.0.1:3900/v1/audio/speech', ['voice' => 'expr-voice-2-f', 'response_format' => 'mp3', 'model' => 'kittentts', 'input' => 'Oh, joy.']],
            // a host without a local voice gets the engine's default one
            ['http://127.0.0.1:3900/v1/audio/speech', ['voice' => 'default', 'response_format' => 'mp3', 'model' => 'kittentts', 'input' => 'Once upon a time.']],
        ], $requests);
    }

    public function testALineVoiceStudioRefusesIsUnspeakable(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('{"error":{"message":"Unknown voice"}}', [
            'http_code' => 400,
            'response_headers' => ['content-type' => 'application/json'],
        ]));

        try {
            $this->voice(SpeechPlatformFactory::create($httpClient, 'http://127.0.0.1:3900', 'kittentts'))
                ->speak(new Script('Episode', [new Line('paul', 'neutral', 'Hello.', [])]));
            self::fail('The line was voiced.');
        } catch (UnspeakableLineException $e) {
            self::assertSame(1, $e->lineNumber);
            self::assertStringContainsString('Unknown voice', $e->getMessage());
        }
    }

    private function voice(PlatformInterface $voiceStudio): Voice
    {
        return new Voice(
            new ServiceLocator(['voicestudio' => static fn (): PlatformInterface => $voiceStudio]),
            'voicestudio',
            'kittentts',
            ['paul' => 'expr-voice-2-m', 'jane' => 'expr-voice-2-f'],
            Fixtures::show(),
        );
    }
}
