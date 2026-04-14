<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

#[Timeout(120)]
class MarkdownSegmentTranslator implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return 'You translate Kenyan educational content from English to Swahili. '
            .'You will receive plain text segments extracted from markdown. '
            .'Translate each segment into Swahili while preserving inline markdown, '
            .'punctuation, numbering, links, emphasis, and code spans exactly as written. '
            .'Do not add or remove items. Do not merge segments. '
            .'Do not translate proper nouns, subject names, or version metadata.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'translations' => $schema->array()
                ->items($schema->string())
                ->required(),
        ];
    }
}
