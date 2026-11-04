<?php

declare(strict_types=1);

namespace App\Episode;

use App\Show\Show;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use UnexpectedValueException;

/**
 * Turns a brief and its sources into a two-host dialogue, each line citing its sources.
 */
final readonly class ScriptWriter
{
    private const WORDS_PER_MINUTE = 150;

    /** @param non-empty-string $model */
    public function __construct(
        private PlatformInterface $platform,
        private Show $show,
        #[Autowire(env: 'WRITER_MODEL')] private string $model,
    ) {
    }

    public function write(Brief $brief): Script
    {
        $hosts = '';
        foreach ($this->show->hosts as $host) {
            $hosts .= \sprintf("- id \"%s\", %s: %s Emotions: %s.\n", $host->id, $host->name, $host->persona, implode(', ', array_keys($host->voices)));
        }

        $sources = '';
        foreach ($brief->sources as $i => $source) {
            $sources .= \sprintf("[%d] %s%s\n%s\n\n", $i + 1, $source->title, $source->url ? " ({$source->url})" : '', $source->notes);
        }

        $system = <<<PROMPT
            You write scripts for "{$this->show->title}", a podcast in language "{$this->show->language}" with two hosts:
            {$hosts}
            Write a lively dialogue that sounds like two friends who know the topic, not a press release:
            - open with a hook, end with a short sign-off;
            - short spoken lines, interruptions, reactions, a bit of humour; the two personalities must clash a little;
            - every fact must come from the numbered sources, and the line must list those source numbers; never add facts, scores or results the sources do not state;
            - opinions and predictions are welcome, but say they are opinions; never state as fact what the sources do not say;
            - write for the ear: no lists, no URLs, no abbreviations a voice would stumble on;
            - talk about teams, companies and groups by their name or league, never by nationality or ethnicity (the voice service's moderation rejects those lines);
            - pick one emotion per line among the host's emotions, matching the line;
            - about {$this->words($brief)} words in total.
            PROMPT;

        $user = "Topic: {$brief->topic}\n".($brief->tone ? "Tone: {$brief->tone}\n" : '')."\nSources:\n\n{$sources}";

        $script = $this->platform->invoke($this->model, new MessageBag(Message::forSystem($system), Message::ofUser($user)), [
            'response_format' => Script::class,
            'temperature' => 0.7,
        ])->asObject();

        if (!$script instanceof Script) {
            throw new UnexpectedValueException(\sprintf('Expected a script, got "%s".', get_debug_type($script)));
        }

        return $script;
    }

    private function words(Brief $brief): int
    {
        return $brief->minutes * self::WORDS_PER_MINUTE;
    }
}
