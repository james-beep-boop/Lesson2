<?php

namespace App\Services;

/**
 * Maps a selected rendered-text snippet back to its byte offsets in the
 * original Markdown source, using surrounding context to disambiguate when
 * the same text appears more than once.
 */
class MarkdownSelectionMatcher
{
    /** Characters of surrounding text to use for context scoring. */
    private const CONTEXT_WINDOW = 120;

    public function find(
        string $markdown,
        string $selectedText,
        string $contextBefore = '',
        string $contextAfter = '',
    ): SelectionMatchResult {
        $needle = trim($selectedText);

        if ($needle === '') {
            return SelectionMatchResult::ambiguous();
        }

        $occurrences = $this->findAll($markdown, $needle);

        if (count($occurrences) === 0) {
            return SelectionMatchResult::ambiguous();
        }

        if (count($occurrences) === 1) {
            $start = $occurrences[0];

            return SelectionMatchResult::confident($start, $start + strlen($needle));
        }

        // Multiple occurrences — score by context similarity.
        $best = $this->scoredBest($markdown, $occurrences, strlen($needle), $contextBefore, $contextAfter);

        if ($best === null) {
            return SelectionMatchResult::ambiguous();
        }

        return SelectionMatchResult::confident($best, $best + strlen($needle));
    }

    /** @return int[] Byte offsets of every non-overlapping occurrence of $needle in $haystack. */
    private function findAll(string $haystack, string $needle): array
    {
        $positions = [];
        $offset = 0;
        $step = strlen($needle);
        while (($pos = strpos($haystack, $needle, $offset)) !== false) {
            $positions[] = $pos;
            $offset = $pos + $step;
        }

        return $positions;
    }

    /**
     * Single-pass context scorer. Returns the best-matching offset, or null
     * when the result is still ambiguous after scoring.
     *
     * Scoring: +1 if contextBefore appears in the preceding window, +1 if
     * contextAfter appears in the following window (max score: 2). Ambiguous
     * if two or more positions share the highest score, or if both contexts
     * are empty.
     *
     * @param  int[]  $positions
     */
    private function scoredBest(
        string $markdown,
        array $positions,
        int $needleLen,
        string $contextBefore,
        string $contextAfter,
    ): ?int {
        $beforeNeedle = trim($contextBefore);
        $afterNeedle = trim($contextAfter);

        if ($beforeNeedle === '' && $afterNeedle === '') {
            return null;
        }

        $len = strlen($markdown);

        // Track best result in a single pass to avoid arsort on large arrays.
        $best = null;
        $bestScore = -1;
        $bestCount = 0; // number of positions that share the current best score

        foreach ($positions as $pos) {
            $precedingStart = max(0, $pos - self::CONTEXT_WINDOW);
            $preceding = substr($markdown, $precedingStart, $pos - $precedingStart);

            $followingEnd = min($len, $pos + $needleLen + self::CONTEXT_WINDOW);
            $following = substr($markdown, $pos + $needleLen, $followingEnd - ($pos + $needleLen));

            $score = 0;
            if ($beforeNeedle !== '' && str_contains($preceding, $beforeNeedle)) {
                $score++;
            }
            if ($afterNeedle !== '' && str_contains($following, $afterNeedle)) {
                $score++;
            }

            if ($score > $bestScore) {
                $best = $pos;
                $bestScore = $score;
                $bestCount = 1;
            } elseif ($score === $bestScore) {
                $bestCount++;
            }

            // Maximum possible score; stop early if already tied at the top.
            if ($bestScore === 2 && $bestCount > 1) {
                return null;
            }
        }

        return $bestCount === 1 ? $best : null;
    }
}
