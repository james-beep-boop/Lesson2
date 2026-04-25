<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

class GuideMarkdown
{
    public static function render(string $markdown): HtmlString
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'external_link' => [
                'internal_hosts' => [parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost'],
                'open_in_new_window' => true,
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new ExternalLinkExtension);

        return new HtmlString((string) (new MarkdownConverter($environment))->convert($markdown));
    }
}
