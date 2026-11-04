<?php

declare(strict_types=1);

namespace App\Episode;

use App\Show\Show;

/**
 * The episode's transcript as WebVTT, one cue per line, tagged with the host's name.
 */
final readonly class Transcript
{
    public function __construct(
        private Show $show,
    ) {
    }

    /**
     * @param list<array{float, float}> $timings when each line starts and ends, in seconds
     */
    public function vtt(Script $script, array $timings): string
    {
        $vtt = "WEBVTT\n";
        foreach ($script->lines as $i => $line) {
            [$start, $end] = $timings[$i];
            $vtt .= \sprintf(
                "\n%d\n%s --> %s\n<v %s>%s\n",
                $i + 1,
                $this->time($start),
                $this->time($end),
                $this->show->hosts[$line->host]->name ?? $line->host,
                // a blank line would end the cue, "-->" would start a new one
                str_replace(["\n", '-->'], [' ', '->'], $line->text),
            );
        }

        return $vtt;
    }

    private function time(float $seconds): string
    {
        $ms = (int) round($seconds * 1000);

        return \sprintf('%02d:%02d:%02d.%03d', intdiv($ms, 3_600_000), intdiv($ms, 60_000) % 60, intdiv($ms, 1000) % 60, $ms % 1000);
    }
}
