<?php

declare(strict_types=1);

namespace App\Episode;

use App\Show\Show;
use Psr\Container\ContainerInterface;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

/**
 * Speaks every line with the host's voice for its emotion.
 *
 * VOICE_PLATFORM picks who speaks: "mistral" (Voxtral, the default) or "voicestudio", a local
 * VoiceStudio server (VOICESTUDIO_URL), where each host has one voice whatever the emotion.
 */
final readonly class Voice
{
    /**
     * @param non-empty-string      $model
     * @param array<string, string> $localVoices host id => VoiceStudio voice
     */
    public function __construct(
        #[AutowireLocator([
            'mistral' => new Autowire(service: 'ai.platform.mistral'),
            'voicestudio' => new Autowire(service: 'app.platform.voicestudio'),
        ])]
        private ContainerInterface $platforms,
        #[Autowire(env: 'VOICE_PLATFORM')] private string $platform,
        #[Autowire(env: 'VOICE_MODEL')] private string $model,
        #[Autowire(param: 'app.voicestudio_voices')] private array $localVoices,
        private Show $show,
    ) {
    }

    /**
     * @return list<string> one MP3 file content per line
     */
    public function speak(Script $script): array
    {
        $platform = $this->platforms->get($this->platform);
        \assert($platform instanceof PlatformInterface);

        // start requests before reading any: the HTTP client runs them concurrently. All of them for Mistral;
        // VoiceStudio has one GPU worker and queues one more request, it refuses (429) any beyond that.
        $window = 'voicestudio' === $this->platform ? 2 : \count($script->lines);
        $results = [];
        $clips = [];
        foreach ($script->lines as $i => $line) {
            $results[$i] = $platform->invoke($this->model, new Text($line->text), $this->options($line));
            if (\count($results) >= $window) {
                $clips[] = $this->read($script, array_key_first($results), $results);
            }
        }
        while ([] !== $results) {
            $clips[] = $this->read($script, array_key_first($results), $results);
        }

        return $clips;
    }

    /**
     * @param array<int, DeferredResult> $results the started requests, by line index; the one read is removed
     */
    private function read(Script $script, int $i, array &$results): string
    {
        try {
            return $results[$i]->asBinary();
        } catch (ExceptionInterface $e) {
            throw new UnspeakableLineException($i + 1, $script->lines[$i]->text, $e);
        } finally {
            unset($results[$i]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function options(Line $line): array
    {
        if ('voicestudio' === $this->platform) {
            return ['voice' => $this->localVoices[$line->host] ?? 'default', 'response_format' => 'mp3'];
        }

        return ['voice' => $this->show->hosts[$line->host]->voiceFor($line->emotion)];
    }
}
