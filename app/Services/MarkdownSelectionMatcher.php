<?php

namespace App\Services;

/**
 * Maps a selected rendered-text snippet back to its character offsets in the
 * original Markdown source, using surrounding context to disambiguate when
 * the same text appears more than once.
 *
 * Searching is performed against a normalized projection of the Markdown
 * (via MarkdownProjector) so that selections of bold, italic, link, heading,
 * and list-item text are correctly resolved even though their rendered form
 * differs from the raw Markdown source.
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

        [$normalizedText, $offsetMap] = MarkdownProjector::project($markdown);

        $occurrences = $this->findAll($normalizedText, $needle);

        if (count($occurrences) === 0) {
            return SelectionMatchResult::ambiguous();
        }

        $needleLen = mb_strlen($needle);

        if (count($occurrences) === 1) {
            [$srcStart, $srcEnd] = $this->mapToSource($occurrences[0], $needleLen, $offsetMap);

            return SelectionMatchResult::confident($srcStart, $srcEnd);
        }

        // Multiple occurrences — score by context similarity using normalized text.
        $bestNormPos = $this->scoredBest($normalizedText, $occurrences, $needleLen, $contextBefore, $contextAfter);

        if ($bestNormPos === null) {
            return SelectionMatchResult::ambiguous();
        }

        [$srcStart, $srcEnd] = $this->mapToSource($bestNormPos, $needleLen, $offsetMap);

        return SelectionMatchResult::confident($srcStart, $srcEnd);
    }

    /**
     * Translate a normalized-text match position back to source offsets.
     *
     * @return array{0: int, 1: int} [$srcStart, $srcEnd]
     */
    private function mapToSource(int $normStart, int $needleLen, array $offsetMap): array
    {
        $normEnd = $normStart + $needleLen - 1;

        $srcStart = $offsetMap[$normStart] ?? 0;
        $srcEnd = isset($offsetMap[$normEnd]) ? $offsetMap[$normEnd] + 1 : $srcStart + $needleLen;

        return [$srcStart, $srcEnd];
    }

    /** @return int[] Unicode character offsets of every non-overlapping occurrence of $needle in $haystack. */
    private function findAll(string $haystack, string $needle): array
    {
        $positions = [];
        $offset = 0;
        $step = mb_strlen($needle);
        while (($pos = mb_strpos($haystack, $needle, $offset)) !== false) {
            $positions[] = $pos;
            $offset = $pos + $step;
        }

        return $positions;
    }

    /**
     * Single-pass context scorer operating on the normalized text.
     *
     * The contextBefore/contextAfter strings come from the browser's rendered
     * text, which corresponds to the normalized form, so comparing them against
     * normalized windows is correct.
     *
     * Returns the best-matching normalized character offset, or null when the
     * result is still ambiguous after scoring.
     *
     * Scoring: +1 if contextBefore appears in the preceding window, +1 if
     * contextAfter appears in the following window (max score: 2). Ambiguous
     * if two or more positions share the highest score, or if both contexts
     * are empty.
     *
     * @param  int[]  $positions  Unicode character offsets into $normalizedText
     */
    private function scoredBest(
        string $normalizedText,
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

        $len = mb_strlen($normalizedText);

        $best = null;
        $bestScore = -1;
        $bestCount = 0;

        foreach ($positions as $pos) {
            $precedingStart = max(0, $pos - self::CONTEXT_WINDOW);
            $preceding = mb_substr($normalizedText, $precedingStart, $pos - $precedingStart);

            $followingEnd = min($len, $pos + $needleLen + self::CONTEXT_WINDOW);
            $following = mb_substr($normalizedText, $pos + $needleLen, $followingEnd - ($pos + $needleLen));

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
