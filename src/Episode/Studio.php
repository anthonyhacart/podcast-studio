<?php

declare(strict_types=1);

namespace App\Episode;

use Symfony\Component\Clock\ClockInterface;

/**
 * The editorial workflow: draft a script from a brief, revise it on request, publish it as audio.
 */
final readonly class Studio
{
    public function __construct(
        private EpisodeRepository $episodes,
        private ScriptWriter $writer,
        private Voice $voice,
        private Mp3Joiner $joiner,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{string, Script} the new episode id and its draft script
     */
    public function draft(Brief $brief): array
    {
        $id = $this->episodes->nextId();
        $this->episodes->saveBrief($id, $brief);
        $script = $this->writer->write($brief);
        $this->episodes->saveScript($id, $script);

        return [$id, $script];
    }

    public function revise(string $id, string $instructions): Script
    {
        $script = $this->writer->revise($this->episodes->brief($id), $this->episodes->script($id), $instructions);
        $this->episodes->saveScript($id, $script);

        return $script;
    }

    public function publish(string $id): Published
    {
        $script = $this->episodes->script($id);
        [$mp3, $seconds] = $this->joiner->join($this->voice->speak($script));
        $episode = new Published($id, $script->title, $seconds, $this->clock->now());
        $this->episodes->savePublished($id, $episode, $mp3);

        return $episode;
    }
}
