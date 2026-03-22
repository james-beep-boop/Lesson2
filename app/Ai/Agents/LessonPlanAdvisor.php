<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

class LessonPlanAdvisor implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are an expert educational content advisor helping teachers '
            . 'in Kenya write clear, effective lesson plans. Provide concise, '
            . 'practical suggestions. Do not rewrite the entire document unless '
            . 'asked — focus on specific, actionable feedback.';
    }
}
