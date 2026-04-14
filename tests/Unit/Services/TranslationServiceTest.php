<?php

use App\Ai\Agents\MarkdownSegmentTranslator;
use App\Services\TranslationService;

test('translation service preserves markdown structure while translating segments', function () {
    MarkdownSegmentTranslator::fake(function ($prompt) {
        $promptText = is_object($prompt) && property_exists($prompt, 'prompt')
            ? $prompt->prompt
            : (string) $prompt;

        preg_match('/\[\s*(.*)\s*\]\s*$/s', $promptText, $matches);

        $segments = json_decode('['.($matches[1] ?? '').']', true, 512, JSON_THROW_ON_ERROR);

        return [
            'translations' => array_map(fn (string $segment) => "sw: {$segment}", $segments),
        ];
    });

    $service = app(TranslationService::class);

    $translated = $service->translatePreservingMarkdown(<<<'MD'
## Heading

| Name | Notes |
| --- | --- |
| Alice | Ready |
MD);

    expect($translated)->toBe(<<<'MD'
## sw: Heading

| sw: Name | sw: Notes |
| --- | --- |
| sw: Alice | sw: Ready |
MD."\n");
});
