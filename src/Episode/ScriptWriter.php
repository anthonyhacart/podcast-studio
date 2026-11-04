<?php

declare(strict_types=1);

namespace App\Episode;

use App\Show\Show;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * Asks the "writer" agent for a script, checks it, and asks once more with the
 * problems found before giving up. Checking the facts against the sources is the
 * job of the review in the MCP client: it gathered them.
 */
final readonly class ScriptWriter
{
    public const WORDS_PER_MINUTE = 150;

    public function __construct(
        #[Target('writer')] private AgentInterface $writer,
        private ScriptChecker $checker,
        private Show $show,
        private ClockInterface $clock,
    ) {
    }

    public function write(Brief $brief): Script
    {
        return $this->askChecked($brief, new MessageBag(Message::ofUser($this->request($brief))));
    }

    /**
     * Rewrites a script following the editor's instructions, keeping the same sources.
     */
    public function revise(Brief $brief, Script $script, string $instructions): Script
    {
        return $this->askChecked($brief, new MessageBag(
            Message::ofUser($this->request($brief)),
            Message::ofAssistant((string) json_encode($script)),
            Message::ofUser("The editor asks for these changes, apply them and return the whole script:\n{$instructions}"),
        ));
    }

    private function askChecked(Brief $brief, MessageBag $messages): Script
    {
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
        return $this->writer->call($messages, ['response_format' => Script::class, 'temperature' => 0.4])->asObject(Script::class);
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
            Today: {$this->clock->now()->format('l j F Y')}.
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
