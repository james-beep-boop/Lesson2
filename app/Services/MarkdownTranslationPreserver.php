<?php

namespace App\Services;

class MarkdownTranslationPreserver
{
    /**
     * Extract translatable text segments while preserving the source markdown
     * structure in a tokenized template.
     *
     * @return array{template: string, segments: array<int, string>}
     */
    public function extract(string $markdown): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];
        $segments = [];
        $templateLines = [];
        $insideFence = false;

        foreach ($lines as $line) {
            if ($this->isFence($line)) {
                $insideFence = ! $insideFence;
                $templateLines[] = $line;

                continue;
            }

            if ($insideFence || $this->isIndentedCode($line) || $this->isHorizontalRule($line) || $this->isHtmlBlock($line)) {
                $templateLines[] = $line;

                continue;
            }

            if ($this->isTableSeparator($line)) {
                $templateLines[] = $line;

                continue;
            }

            if ($this->isTableRow($line)) {
                $templateLines[] = $this->tokenizeTableRow($line, $segments);

                continue;
            }

            $templateLines[] = $this->tokenizeStandardLine($line, $segments);
        }

        return [
            'template' => implode("\n", $templateLines),
            'segments' => $segments,
        ];
    }

    /**
     * @param  array<int, string>  $translations
     */
    public function rebuild(string $template, array $translations): string
    {
        $rebuilt = $template;

        foreach ($translations as $index => $translation) {
            $rebuilt = str_replace($this->token($index), $translation, $rebuilt);
        }

        return $rebuilt;
    }

    private function tokenizeStandardLine(string $line, array &$segments): string
    {
        if (trim($line) === '') {
            return $line;
        }

        if (preg_match('/^(\s*(?:>\s*)*)(#{1,6}\s+)(.*)$/', $line, $matches)) {
            return $matches[1].$matches[2].$this->storeSegment($matches[3], $segments);
        }

        if (preg_match('/^(\s*(?:>\s*)*)([*+-]\s+(?:\[[ xX]\]\s+)?|\d+\.\s+)(.*)$/', $line, $matches)) {
            return $matches[1].$matches[2].$this->storeSegment($matches[3], $segments);
        }

        if (preg_match('/^(\s*(?:>\s*)+)(.*)$/', $line, $matches)) {
            return $matches[1].$this->storeSegment($matches[2], $segments);
        }

        return $this->storeSegment($line, $segments);
    }

    private function tokenizeTableRow(string $line, array &$segments): string
    {
        $parts = explode('|', $line);

        foreach ($parts as $index => $part) {
            $isEdgeCell = ($index === 0 || $index === count($parts) - 1) && trim($part) === '';

            if ($isEdgeCell) {
                continue;
            }

            preg_match('/^(\s*)(.*?)(\s*)$/', $part, $matches);

            $inner = $matches[2] ?? '';

            if ($inner === '' || $this->isTableSeparatorCell($inner)) {
                continue;
            }

            $parts[$index] = ($matches[1] ?? '')
                .$this->storeSegment($inner, $segments)
                .($matches[3] ?? '');
        }

        return implode('|', $parts);
    }

    private function storeSegment(string $segment, array &$segments): string
    {
        if ($segment === '') {
            return '';
        }

        $segments[] = $segment;

        return $this->token(count($segments) - 1);
    }

    private function token(int $index): string
    {
        return "__ARES_TRANSLATION_SEGMENT_{$index}__";
    }

    private function isFence(string $line): bool
    {
        return preg_match('/^\s*(```|~~~)/', $line) === 1;
    }

    private function isIndentedCode(string $line): bool
    {
        return preg_match('/^(?: {4}|\t)/', $line) === 1;
    }

    private function isHorizontalRule(string $line): bool
    {
        return preg_match('/^\s{0,3}(?:\*\s*){3,}$|^\s{0,3}(?:-\s*){3,}$|^\s{0,3}(?:_\s*){3,}$/', $line) === 1;
    }

    private function isHtmlBlock(string $line): bool
    {
        return preg_match('/^\s*<[^>]+>/', $line) === 1;
    }

    private function isTableSeparator(string $line): bool
    {
        $trimmed = trim($line);

        return str_contains($trimmed, '|')
            && preg_match('/^\|?(\s*:?-{3,}:?\s*\|)+\s*:?-{3,}:?\s*\|?$/', $trimmed) === 1;
    }

    private function isTableRow(string $line): bool
    {
        $trimmed = trim($line);

        return str_contains($trimmed, '|') && ! $this->isTableSeparator($line);
    }

    private function isTableSeparatorCell(string $value): bool
    {
        return preg_match('/^:?-{3,}:?$/', trim($value)) === 1;
    }
}
