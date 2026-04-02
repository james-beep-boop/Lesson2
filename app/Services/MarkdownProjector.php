<?php

namespace App\Services;

/**
 * Projects Markdown source text into a normalized visible-text representation,
 * producing an offset map so each character in the normalized text can be
 * traced back to its position in the original Markdown source.
 *
 * This approximates what a browser renders: inline markers (**, __, *, _, ``,
 * ~~, [text](url), ![alt](url)) are stripped, line-level prefixes (headings,
 * blockquotes, list markers) are skipped, and paragraph breaks are collapsed.
 */
class MarkdownProjector
{
    /**
     * Project Markdown source into normalized text + offset map.
     *
     * @return array{0: string, 1: int[]} [$normalizedText, $offsetMap]
     *                                    where $offsetMap[i] is the character offset of normalized char i in $markdown.
     */
    public static function project(string $markdown): array
    {
        if ($markdown === '') {
            return ['', []];
        }

        $normalized = '';
        $map = [];

        $lines = explode("\n", $markdown);
        $srcOffset = 0;

        foreach ($lines as $lineIdx => $rawLine) {
            $rawLineLen = mb_strlen($rawLine);

            // Detect horizontal rules — emit nothing, just skip the line.
            if (preg_match('/^(\*{3,}|-{3,}|_{3,})\s*$/u', $rawLine)) {
                $srcOffset += $rawLineLen + 1;
                // Still add separator newline if not last line.
                if ($lineIdx < count($lines) - 1) {
                    $normalized .= "\n";
                    $map[] = $srcOffset - 1;
                }

                continue;
            }

            // Detect fenced code block markers (```) — emit nothing for the fence line.
            if (preg_match('/^(`{3,}|~{3,})/u', $rawLine)) {
                $srcOffset += $rawLineLen + 1;
                if ($lineIdx < count($lines) - 1) {
                    $normalized .= "\n";
                    $map[] = $srcOffset - 1;
                }

                continue;
            }

            $line = $rawLine;
            $linePrefix = 0;

            // Heading: ^#{1,6}\s+
            if (preg_match('/^(#{1,6}\s+)/u', $line, $m)) {
                $skip = mb_strlen($m[1]);
                $linePrefix = $skip;
                $line = mb_substr($line, $skip);
            }
            // Blockquote: ^>\s?
            elseif (preg_match('/^(>\s?)/u', $line, $m)) {
                $skip = mb_strlen($m[1]);
                $linePrefix = $skip;
                $line = mb_substr($line, $skip);
            }
            // List item: leading spaces + marker + space(s)
            elseif (preg_match('/^(\s*(?:[-*+]|\d+\.)\s+)/u', $line, $m)) {
                $skip = mb_strlen($m[1]);
                $linePrefix = $skip;
                $line = mb_substr($line, $skip);
            }

            // Process inline Markdown on the content portion of the line.
            [$inlineNorm, $inlineOffsets] = self::projectInline($line);

            // Adjust offsets: srcOffset is start of rawLine in source, linePrefix
            // is how many characters we skipped at the beginning of the line.
            foreach ($inlineOffsets as $relOffset) {
                $map[] = $srcOffset + $linePrefix + $relOffset;
            }

            $normalized .= $inlineNorm;

            // Advance srcOffset past this line plus the \n separator.
            $srcOffset += $rawLineLen + 1;

            // Emit a newline between lines (not after the very last line).
            if ($lineIdx < count($lines) - 1) {
                $normalized .= "\n";
                // Map the newline to the \n in source (srcOffset - 1 after increment).
                $map[] = $srcOffset - 1;
            }
        }

        return [$normalized, $map];
    }

    /**
     * Process inline Markdown in a single line, returning normalized text and
     * a per-character offset array (offsets relative to start of $text).
     *
     * @return array{0: string, 1: int[]}
     */
    private static function projectInline(string $text): array
    {
        $result = '';
        $offsets = [];
        $len = mb_strlen($text);
        $i = 0;

        while ($i < $len) {
            $remaining = mb_substr($text, $i);

            // Image: ![alt](url)  — must come before link check
            if (preg_match('/^!\[([^\]]*)\]\([^\)]*\)/su', $remaining, $m)) {
                $content = $m[1];
                $contentLen = mb_strlen($content);
                for ($j = 0; $j < $contentLen; $j++) {
                    $offsets[] = $i + 2 + $j; // +2 for "!["
                }
                $result .= $content;
                $i += mb_strlen($m[0]);

                continue;
            }

            // Link: [text](url)
            if (preg_match('/^\[([^\]]*)\]\([^\)]*\)/su', $remaining, $m)) {
                $content = $m[1];
                $contentLen = mb_strlen($content);
                for ($j = 0; $j < $contentLen; $j++) {
                    $offsets[] = $i + 1 + $j; // +1 for "["
                }
                $result .= $content;
                $i += mb_strlen($m[0]);

                continue;
            }

            // Bold: **...** or __...__
            if (preg_match('/^(\*\*|__)(.+?)\1/su', $remaining, $m)) {
                $delim = mb_strlen($m[1]);
                $content = $m[2];
                $contentLen = mb_strlen($content);
                for ($j = 0; $j < $contentLen; $j++) {
                    $offsets[] = $i + $delim + $j;
                }
                $result .= $content;
                $i += mb_strlen($m[0]);

                continue;
            }

            // Italic: *...* or _..._  (single delimiter, not double)
            if (preg_match('/^(\*|_)(.+?)\1/su', $remaining, $m)) {
                $delim = mb_strlen($m[1]);
                $content = $m[2];
                $contentLen = mb_strlen($content);
                for ($j = 0; $j < $contentLen; $j++) {
                    $offsets[] = $i + $delim + $j;
                }
                $result .= $content;
                $i += mb_strlen($m[0]);

                continue;
            }

            // Inline code: `...`
            if (preg_match('/^`([^`]+)`/su', $remaining, $m)) {
                $content = $m[1];
                $contentLen = mb_strlen($content);
                for ($j = 0; $j < $contentLen; $j++) {
                    $offsets[] = $i + 1 + $j; // +1 for opening backtick
                }
                $result .= $content;
                $i += mb_strlen($m[0]);

                continue;
            }

            // Strikethrough: ~~...~~
            if (preg_match('/^~~(.+?)~~/su', $remaining, $m)) {
                $content = $m[1];
                $contentLen = mb_strlen($content);
                for ($j = 0; $j < $contentLen; $j++) {
                    $offsets[] = $i + 2 + $j;
                }
                $result .= $content;
                $i += mb_strlen($m[0]);

                continue;
            }

            // Escaped character: \* \_ \` etc. — emit the literal char, map to it (not the backslash)
            if (preg_match('/^\\\\([*_`~\[\]\\\\!])/u', $remaining, $m)) {
                $offsets[] = $i + 1; // position of the escaped char
                $result .= $m[1];
                $i += 2;

                continue;
            }

            // HTML tag: skip entirely (no output, no map entry)
            if (preg_match('/^<[^>]+>/su', $remaining, $m)) {
                $i += mb_strlen($m[0]);

                continue;
            }

            // Regular character — emit as-is, map to its own position.
            $ch = mb_substr($text, $i, 1);
            $offsets[] = $i;
            $result .= $ch;
            $i++;
        }

        return [$result, $offsets];
    }
}
