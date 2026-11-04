<?php

declare(strict_types=1);

namespace App\Episode;

use App\Show\Show;
use RuntimeException;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Speaks every line with the host's voice for its emotion.
 */
final readonly class Voice
{
    /** @param non-empty-string $model */
    public function __construct(
        private PlatformInterface $platform,
        private Show $show,
        #[Autowire(env: 'VOICE_MODEL')] private string $model,
    ) {
    }

    /**
     * @return list<string> one WAV file content per line
     */
    public function speak(Script $script): array
    {
        // start every request before reading any: the HTTP client runs them concurrently
        $results = [];
        foreach ($script->lines as $line) {
            $results[] = $this->platform->invoke($this->model, new Text($line->text), [
                'voice' => $this->show->hosts[$line->host]->voiceFor($line->emotion),
                'response_format' => 'wav',
            ]);
        }

        $clips = [];
        foreach ($results as $i => $result) {
            try {
                $clips[] = $result->asBinary();
            } catch (ExceptionInterface $e) {
                throw new RuntimeException(\sprintf('Line %d could not be voiced ("%s"): %s', $i + 1, $script->lines[$i]->text, $e->getMessage()), previous: $e);
            }
        }

        return $clips;
    }
}
