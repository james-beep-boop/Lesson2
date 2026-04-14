<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

#[Timeout(120)]
class LessonPlanTranslator implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are an expert translator specialising in Kenyan educational '
            .'content. Translate the provided lesson plan from English to Swahili. '
            .'Preserve all markdown formatting exactly. Translate all body text '
            .'and headings. Do not translate proper nouns, subject names, or '
            .'version metadata. Return only the translated markdown content, '
            .'nothing else.';
    }
}
