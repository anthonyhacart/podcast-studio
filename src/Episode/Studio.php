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

    /**
     * When the voice service refuses a line (its moderation flags lines on any topic,
     * sometimes wrongly), the writer rephrases that line and the episode is voiced again, once.
     */
    public function publish(string $id): Published
    {
        $script = $this->episodes->script($id);
        $rephrased = null;
        try {
            $clips = $this->voice->speak($script);
        } catch (UnspeakableLineException $e) {
            $script = $this->writer->revise($this->episodes->brief($id), $script, \sprintf(
                'The voice service refused line %d ("%s"), most likely its content moderation. Rephrase only that line, keeping its meaning, so that it cannot be read as targeting a group of people; keep every other line exactly as it is.',
                $e->lineNumber,
                $e->text,
            ));
            $this->episodes->saveScript($id, $script);
            $rephrased = \sprintf('Line %d, refused by the voice service, now reads: "%s"', $e->lineNumber, $script->lines[$e->lineNumber - 1]->text ?? '');
            $clips = $this->voice->speak($script);
        }

        [$mp3, $seconds] = $this->joiner->join($clips);
        $episode = new Published($id, $script->title, $seconds, $this->clock->now(), $rephrased);
        $this->episodes->savePublished($id, $episode, $mp3);

        return $episode;
    }
}
