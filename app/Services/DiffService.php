<?php

namespace App\Services;

use Jfcherng\Diff\DiffHelper;

class DiffService
{
    /**
     * Generate an HTML side-by-side diff between two Markdown content strings.
     * Returns ['html' => string, 'css' => string].
     *
     * @return array{html: string, css: string}
     */
    public function sideBySide(string $old, string $new): array
    {
        $html = DiffHelper::calculate(
            $old,
            $new,
            'SideBySide',
            ['context' => 5, 'ignoreWhitespace' => false],
            ['detailLevel' => 'word', 'lineNumbers' => false, 'separateBlock' => true]
        );

        return ['html' => $html, 'css' => DiffHelper::getStyleSheet()];
    }

    /**
     * Generate an HTML unified (stacked) diff.
     *
     * @return array{html: string, css: string}
     */
    public function unified(string $old, string $new): array
    {
        $html = DiffHelper::calculate(
            $old,
            $new,
            'Unified',
            ['context' => 5, 'ignoreWhitespace' => false],
            ['detailLevel' => 'word', 'lineNumbers' => false, 'separateBlock' => true]
        );

        return ['html' => $html, 'css' => DiffHelper::getStyleSheet()];
    }
}
