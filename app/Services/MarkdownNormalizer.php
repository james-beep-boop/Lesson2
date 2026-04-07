<?php

namespace App\Services;

class MarkdownNormalizer
{
    /**
     * Normalize markdown content for consistent storage.
     *
     * Applies only non-semantic transformations:
     * 1. Unify line endings: CRLF and lone CR → LF (single pass)
     * 2. Ensure a single trailing newline on non-empty strings
     *
     * Empty string in → empty string out.
     * Idempotent: normalizing twice produces the same result as normalizing once.
     */
    public function normalize(string $content): string
    {
        if ($content === '') {
            return '';
        }

        // Single-pass: replace CRLF first, then any remaining lone CR
        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);

        // Ensure exactly one trailing newline
        $content = rtrim($content, "\n")."\n";

        return $content;
    }
}
