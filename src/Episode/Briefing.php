<?php

declare(strict_types=1);

namespace App\Episode;

/**
 * Makes a brief of what an editor filled in in the browser: the links are read, the
 * researcher searches when asked to, the notes are a source like the others. It does
 * here what an MCP client does before calling draft_episode.
 */
final readonly class Briefing
{
    public function __construct(
        private SourceReader $reader,
        private Researcher $researcher,
    ) {
    }

    /**
     * @throws UnreadableSourceException when a link of the editor cannot be read
     * @throws NothingFoundException     when the researcher found no page it could read
     */
    public function brief(NewEpisode $episode): Brief
    {
        $questions = array_values($episode->questions);

        $sources = array_map($this->reader->read(...), $episode->links);
        if ($episode->search) {
            foreach ($this->researcher->research($episode->topic, $questions) as $found) {
                // not a page the editor already gave
                if (!\in_array($found->url, $episode->links, true)) {
                    $sources[] = $found;
                }
            }
        }
        if (null !== $episode->notes) {
            $sources[] = new Source('Notes from the editor', $episode->notes);
        }

        return new Brief($episode->topic, $sources, $episode->minutes, $episode->tone, $questions, $episode->hosts);
    }
}
