<?php

declare(strict_types=1);

namespace App\Episode;

use App\Show\Show;

/**
 * Rules the writer must follow, checked in code. A cited source proves a line is
 * backed by something, not that it is faithful to it: that is the human review's job.
 */
final readonly class ScriptChecker
{
    private const MIN_LENGTH_RATIO = 0.6;
    private const MAX_LENGTH_RATIO = 1.5;
    private const MAX_LINE_CHARACTERS = 400;
    private const MIN_CHAPTERS = 2;
    private const MAX_CHAPTERS = 6;
    // episodes are heard later: relative days would be wrong by then
    private const RELATIVE_DAYS = '/\b(today|tonight|tomorrow|yesterday|this (?:week|weekend|morning|evening)|last (?:night|week)|next week)\b/i';

    public function __construct(
        private Show $show,
    ) {
    }

    /**
     * @return list<string> problems, empty when the script is fine
     */
    public function check(Script $script, Brief $brief): array
    {
        $problems = [];
        $words = 0;
        $cited = false;

        if ([] === $script->lines) {
            return ['The script has no lines.'];
        }

        foreach ($script->lines as $i => $line) {
            $n = $i + 1;
            $host = $this->show->hosts[$line->host] ?? null;
            if (null === $host) {
                $problems[] = \sprintf('Line %d: unknown host "%s" (hosts: %s).', $n, $line->host, implode(', ', array_keys($this->show->hosts)));
            } elseif (!isset($host->voices[$line->emotion])) {
                $problems[] = \sprintf('Line %d: %s has no emotion "%s" (emotions: %s).', $n, $host->name, $line->emotion, implode(', ', array_keys($host->voices)));
            }
            if ('' === trim($line->text)) {
                $problems[] = \sprintf('Line %d is empty.', $n);
            }
            if (preg_match(self::RELATIVE_DAYS, $line->text, $match)) {
                $problems[] = \sprintf('Line %d says "%s": give the date itself, people listen later.', $n, $match[1]);
            }
            if (mb_strlen($line->text) > self::MAX_LINE_CHARACTERS) {
                $problems[] = \sprintf('Line %d is too long for a spoken line (%d characters, max %d).', $n, mb_strlen($line->text), self::MAX_LINE_CHARACTERS);
            }
            foreach ($line->sources as $source) {
                if ($source < 1 || $source > \count($brief->sources)) {
                    $problems[] = \sprintf('Line %d cites source %d, which does not exist (sources: 1 to %d).', $n, $source, \count($brief->sources));
                }
            }
            $cited = $cited || [] !== $line->sources;
            $words += str_word_count($line->text);
        }

        $chapters = \count(array_filter($script->lines, static fn (Line $line) => null !== $line->chapter));
        if (null === $script->lines[0]->chapter) {
            $problems[] = 'Line 1 must open a chapter.';
        }
        if ($chapters < self::MIN_CHAPTERS || $chapters > self::MAX_CHAPTERS) {
            $problems[] = \sprintf('The script has %d chapters, make %d to %d.', $chapters, self::MIN_CHAPTERS, self::MAX_CHAPTERS);
        }
        if ('' === trim($script->summary)) {
            $problems[] = 'The summary is empty.';
        }

        if (!$cited) {
            $problems[] = 'No line cites a source: every fact must come from the sources.';
        }

        $target = $brief->minutes * ScriptWriter::WORDS_PER_MINUTE;
        if ($words < $target * self::MIN_LENGTH_RATIO || $words > $target * self::MAX_LENGTH_RATIO) {
            $problems[] = \sprintf('The script has %d words, aim for about %d.', $words, $target);
        }

        return $problems;
    }
}
