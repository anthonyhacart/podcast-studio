<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Archive\PastEpisodes;
use App\Episode\AlreadyPublishingException;
use App\Episode\Brief;
use App\Episode\InvalidScriptException;
use App\Episode\Script;
use App\Episode\Source;
use App\Episode\Studio;
use App\Episode\UnspeakableLineException;
use App\Mailbox\Mailbox;
use App\Mailbox\Question;
use App\Show\Show;
use InvalidArgumentException;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;

/**
 * The control room: the MCP client brings the topic and the sources, reviews the
 * script with the user, then publishes.
 */
final readonly class EpisodeTools
{
    public function __construct(
        private Studio $studio,
        private Show $show,
        private PastEpisodes $pastEpisodes,
        private Mailbox $mailbox,
    ) {
    }

    /**
     * @param string                                                  $topic     what the episode is about
     * @param list<array{title: string, notes: string, url?: string}> $sources   what you found on the topic: the hosts only use these facts, so put every fact they need in the notes
     * @param int                                                     $minutes   target length, 1 to 5
     * @param string|null                                             $tone      e.g. "fun, for fans who missed the news"
     * @param list<string>                                            $questions ids of listener questions (listener_questions) the episode answers
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'draft_episode', description: 'Write the script of a new podcast episode from a topic and the sources you gathered. Show the script to the user for review before publishing.')]
    public function draft(
        string $topic,
        #[Schema(minItems: 1, items: [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'notes' => ['type' => 'string', 'description' => 'the facts from this source, as plain text'],
                'url' => ['type' => 'string'],
            ],
            'required' => ['title', 'notes'],
        ])]
        array $sources,
        #[Schema(minimum: 1, maximum: 5)]
        int $minutes = 2,
        ?string $tone = null,
        #[Schema(items: ['type' => 'string'])]
        array $questions = [],
    ): array {
        return $this->tell(function () use ($topic, $sources, $minutes, $tone, $questions) {
            $brief = new Brief(
                $topic,
                array_map(static fn (array $s) => new Source($s['title'], $s['notes'], $s['url'] ?? null), $sources),
                $minutes,
                $tone,
                array_map($this->mailbox->question(...), $questions),
            );
            [$id, $script] = $this->studio->draft($brief);

            return $this->review($id, $script);
        });
    }

    /**
     * @param string $id           the episode id returned by draft_episode
     * @param string $instructions what to change, in the editor's words
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'revise_episode', description: 'Rewrite the script of a draft episode following the editor\'s instructions (cut, shorten, change the tone, fix a fact).')]
    public function revise(string $id, string $instructions): array
    {
        return $this->tell(fn () => $this->review($id, $this->studio->revise($id, $instructions)));
    }

    /**
     * @param string $id the episode id, once the user approved the script
     *
     * @return array{id: string, title: string, duration: string, status: 'published', rephrased?: string}
     */
    #[McpTool(name: 'publish_episode', description: 'Record the approved script with the hosts\' voices and publish the episode. Only call it once the user approved the script.')]
    public function publish(string $id): array
    {
        $episode = $this->tell(fn () => $this->studio->publish($id));

        return [
            'id' => $episode->id,
            'title' => $episode->title,
            'duration' => $episode->duration(),
            'status' => 'published',
        ] + ($episode->rephrased ? ['rephrased' => $episode->rephrased.'. Tell the user.'] : []);
    }

    /**
     * @return list<array{id: string, from: string, question: string, asked: string}>
     */
    #[McpTool(name: 'listener_questions', description: 'List the questions listeners asked the hosts by voice on the show\'s page, waiting for an episode. Pass the ids of those an episode should answer to draft_episode, with sources that answer them.')]
    public function questions(): array
    {
        return array_map(static fn (Question $q) => [
            'id' => $q->id,
            'from' => $q->name ?? 'a listener',
            'question' => $q->text,
            'asked' => $q->askedAt->format('j F Y'),
        ], $this->mailbox->waiting());
    }

    /**
     * @param string $query a topic, a name or a claim
     *
     * @return list<string>
     */
    #[McpTool(name: 'search_episodes', description: 'Search what the hosts said in the published episodes, to know whether a topic was already covered before drafting a new episode.')]
    public function search(string $query): array
    {
        return ($this->pastEpisodes)($query);
    }

    /**
     * Turns the failures the client can act on into tool errors it can read.
     *
     * @template T
     *
     * @param callable(): T $action
     *
     * @return T
     */
    private function tell(callable $action): mixed
    {
        try {
            return $action();
        } catch (InvalidArgumentException|InvalidScriptException|UnspeakableLineException|AlreadyPublishingException $e) {
            throw new ToolCallException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function review(string $id, Script $script): array
    {
        $lines = [];
        foreach ($script->lines as $line) {
            if (null !== $line->chapter) {
                $lines[] = "## {$line->chapter}";
            }
            $lines[] = \sprintf('%s (%s)%s: %s', $this->show->hosts[$line->host]->name ?? $line->host, $line->emotion, [] === $line->sources ? '' : ' [sources '.implode(', ', $line->sources).']', $line->text);
        }

        return [
            'id' => $id,
            'title' => $script->title,
            'summary' => $script->summary,
            'script' => implode("\n", $lines),
            'next' => 'Before showing the script, compare each line and the summary with the sources you provided and point out to the user any claim they do not state (wrong team, count or date). Then call revise_episode with the fixes and the user\'s changes, or publish_episode once the user approves.',
        ];
    }
}
