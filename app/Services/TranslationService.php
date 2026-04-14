<?php

namespace App\Services;

use App\Ai\Agents\MarkdownSegmentTranslator;
use RuntimeException;

class TranslationService
{
    public function __construct(
        protected MarkdownTranslationPreserver $preserver,
        protected MarkdownNormalizer $normalizer,
    ) {}

    public function translatePreservingMarkdown(string $content): string
    {
        set_time_limit(120);

        $extracted = $this->preserver->extract($content);
        $segments = $extracted['segments'];

        if ($segments === []) {
            return $this->normalizer->normalize($content);
        }

        $translations = [];

        foreach (array_chunk($segments, 40) as $chunk) {
            $response = MarkdownSegmentTranslator::make()->prompt(
                $this->buildPrompt($chunk)
            );

            $translatedChunk = $response['translations'] ?? [];

            if (count($translatedChunk) !== count($chunk)) {
                throw new RuntimeException('The AI translator returned an unexpected number of translated markdown segments.');
            }

            array_push($translations, ...$translatedChunk);
        }

        return $this->normalizer->normalize(
            $this->preserver->rebuild($extracted['template'], $translations)
        );
    }

    /**
     * @param  array<int, string>  $segments
     */
    protected function buildPrompt(array $segments): string
    {
        return "Translate each JSON segment value into Swahili and return them in the same order.\n"
            ."Do not change markdown punctuation inside the text.\n"
            ."JSON segments:\n"
            .json_encode($segments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
