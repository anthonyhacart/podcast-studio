<?php

declare(strict_types=1);

namespace App\Episode;

use App\Show\Show;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * Asks the "writer" agent for a script, checks it, and asks once more with the
 * problems found before giving up.
 */
final readonly class ScriptWriter
{
    public const WORDS_PER_MINUTE = 150;

    public function __construct(
        private AgentInterface $writer,
        private ScriptChecker $checker,
        private Show $show,
    ) {
    }

    public function write(Brief $brief): Script
    {
        $messages = new MessageBag(Message::ofUser($this->request($brief)));
        $script = $this->ask($messages);

        if ([] !== $problems = $this->checker->check($script, $brief)) {
            $messages = $messages
                ->with(Message::ofAssistant((string) json_encode($script)))
                ->with(Message::ofUser("Problems found in your script:\n- ".implode("\n- ", $problems)));
            $script = $this->ask($messages);

            if ([] !== $problems = $this->checker->check($script, $brief)) {
                throw new InvalidScriptException($script, $problems);
            }
        }

        return $script;
    }

    private function ask(MessageBag $messages): Script
    {
        return $this->writer->call($messages, ['response_format' => Script::class, 'temperature' => 0.7])->asObject(Script::class);
    }

    private function request(Brief $brief): string
    {
        $hosts = '';
        foreach ($this->show->hosts as $host) {
            $hosts .= \sprintf("- id \"%s\", %s: %s Emotions: %s.\n", $host->id, $host->name, $host->persona, implode(', ', array_keys($host->voices)));
        }

        $sources = '';
        foreach ($brief->sources as $i => $source) {
            $sources .= \sprintf("[%d] %s%s\n%s\n\n", $i + 1, $source->title, $source->url ? " ({$source->url})" : '', $source->notes);
        }

        $tone = $brief->tone ?? "the show's usual tone";
        $words = $brief->minutes * self::WORDS_PER_MINUTE;

        return <<<MESSAGE
            Show: "{$this->show->title}", in language "{$this->show->language}".
            Hosts:
            {$hosts}
            Topic: {$brief->topic}
            Tone: {$tone}
            Length: about {$words} words.

            Sources:

            {$sources}
            MESSAGE;
    }
}
