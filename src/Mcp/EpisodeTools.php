<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Archive\PastEpisodes;
use App\Episode\Brief;
use App\Episode\InvalidScriptException;
use App\Episode\Script;
use App\Episode\Source;
use App\Episode\Studio;
use App\Episode\UnspeakableLineException;
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
    ) {
    }

    /**
     * @param string                                                  $topic   what the episode is about
     * @param list<array{title: string, notes: string, url?: string}> $sources what you found on the topic: the hosts only use these facts, so put every fact they need in the notes
     * @param int                                                     $minutes target length, 1 to 5
     * @param string|null                                             $tone    e.g. "fun, for fans who missed the news"
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
        int $minutes = 2,
        ?string $tone = null,
    ): array {
        $brief = new Brief($topic, array_map(static fn (array $s) => new Source($s['title'], $s['notes'], $s['url'] ?? null), $sources), max(1, min(5, $minutes)), $tone);

        return $this->tell(function () use ($brief) {
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
        } catch (InvalidArgumentException|InvalidScriptException|UnspeakableLineException $e) {
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
            $lines[] = \sprintf('%s (%s)%s: %s', $this->show->hosts[$line->host]->name ?? $line->host, $line->emotion, [] === $line->sources ? '' : ' [sources '.implode(', ', $line->sources).']', $line->text);
        }

        return [
            'id' => $id,
            'title' => $script->title,
            'script' => implode("\n", $lines),
            'next' => 'Before showing the script, compare each line with the sources you provided and point out to the user any claim they do not state (wrong team, count or date). Then call revise_episode with the fixes and the user\'s changes, or publish_episode once the user approves.',
        ];
    }
}
