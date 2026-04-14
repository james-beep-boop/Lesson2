<?php

use App\Services\MarkdownTranslationPreserver;

test('markdown translation preserver keeps table structure intact', function () {
    $preserver = new MarkdownTranslationPreserver;

    $markdown = <<<'MD'
| Column A | Column B |
| --- | --- |
| Cell one | Cell two |
MD;

    $extracted = $preserver->extract($markdown);

    expect($extracted['segments'])->toBe([
        'Column A',
        'Column B',
        'Cell one',
        'Cell two',
    ]);

    $rebuilt = $preserver->rebuild($extracted['template'], [
        'Safu A',
        'Safu B',
        'Seli ya kwanza',
        'Seli ya pili',
    ]);

    expect($rebuilt)->toBe(<<<'MD'
| Safu A | Safu B |
| --- | --- |
| Seli ya kwanza | Seli ya pili |
MD);
});

test('markdown translation preserver leaves fenced code blocks unchanged', function () {
    $preserver = new MarkdownTranslationPreserver;

    $markdown = <<<'MD'
# Heading

```php
echo 'Do not translate';
```
MD;

    $extracted = $preserver->extract($markdown);

    expect($extracted['segments'])->toBe([
        'Heading',
    ]);
});
