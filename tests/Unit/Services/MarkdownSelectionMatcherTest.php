<?php

use App\Services\MarkdownSelectionMatcher;

beforeEach(function () {
    $this->matcher = new MarkdownSelectionMatcher;
});

// ---------------------------------------------------------------------------
// Basic / plain-text behaviour (kept from original suite)
// ---------------------------------------------------------------------------

it('returns ambiguous for empty selection', function () {
    $result = $this->matcher->find('# Hello world', '   ');
    expect($result->confident)->toBeFalse();
});

it('returns ambiguous when text not found in markdown', function () {
    $result = $this->matcher->find('# Hello world', 'something else entirely');
    expect($result->confident)->toBeFalse();
});

it('returns confident with correct offsets for unique plain text', function () {
    $markdown = "# Title\n\nThis is a paragraph with some unique content here.";
    $result = $this->matcher->find($markdown, 'unique content');

    expect($result->confident)->toBeTrue();
    expect(substr($markdown, $result->start, $result->end - $result->start))->toBe('unique content');
});

it('returns confident for plain text at start of markdown', function () {
    $markdown = 'Hello world and more text';
    $result = $this->matcher->find($markdown, 'Hello world');

    expect($result->confident)->toBeTrue();
    expect($result->start)->toBe(0);
    expect($result->end)->toBe(11);
});

it('returns confident for plain text at end of markdown', function () {
    $markdown = 'Some text and then the end';
    $result = $this->matcher->find($markdown, 'the end');

    expect($result->confident)->toBeTrue();
    expect($result->end)->toBe(mb_strlen($markdown));
});

it('returns ambiguous when duplicates exist and no context provided', function () {
    $markdown = "apple\n\nsome filler text\n\napple";
    $result = $this->matcher->find($markdown, 'apple');

    expect($result->confident)->toBeFalse();
});

it('resolves duplicate using context before', function () {
    $padding = str_repeat('x', 200);
    $markdown = "apple first occurrence\n\n{$padding}\n\nonly before second apple\n\napple second occurrence";
    $result = $this->matcher->find($markdown, 'apple', 'only before second apple', '');

    expect($result->confident)->toBeTrue();
    expect($result->start)->toBeGreaterThan(50);
});

it('resolves duplicate using context after', function () {
    $padding = str_repeat('x', 200);
    $markdown = "apple first occurrence\n\n{$padding}\n\napple second occurrence only here";
    $result = $this->matcher->find($markdown, 'apple', '', 'second occurrence only here');

    expect($result->confident)->toBeTrue();
    expect(mb_substr($markdown, $result->start, $result->end - $result->start))->toBe('apple');
    expect($result->start)->toBeGreaterThan(50);
});

it('remains ambiguous when both duplicates match equally well', function () {
    $padding = str_repeat('x', 200);
    $sharedContext = 'unique phrase here';
    $markdown = "{$sharedContext}\n\napple\n\n{$padding}\n\n{$sharedContext}\n\napple";
    $result = $this->matcher->find($markdown, 'apple', $sharedContext, '');

    expect($result->confident)->toBeFalse();
});

it('handles multiline selection', function () {
    $markdown = "# Heading\n\nFirst line\nSecond line\n\nOther text";
    $result = $this->matcher->find($markdown, "First line\nSecond line");

    expect($result->confident)->toBeTrue();
    expect(mb_substr($markdown, $result->start, $result->end - $result->start))->toBe("First line\nSecond line");
});

it('trims whitespace from selected text before matching', function () {
    $markdown = 'The quick brown fox jumps';
    $result = $this->matcher->find($markdown, '  quick brown  ');

    expect($result->confident)->toBeTrue();
    expect(mb_substr($markdown, $result->start, $result->end - $result->start))->toBe('quick brown');
});

// ---------------------------------------------------------------------------
// Markdown-aware matching (new tests using MarkdownProjector)
// ---------------------------------------------------------------------------

it('matches plain text exactly', function () {
    $md = "Hello world\nThis is a test.";
    $result = (new MarkdownSelectionMatcher)->find($md, 'world', '', '');

    expect($result->confident)->toBeTrue()
        ->and(mb_substr($md, $result->start, $result->end - $result->start))->toBe('world');
});

it('matches text inside bold markers', function () {
    $md = 'This is **important** text.';
    $result = (new MarkdownSelectionMatcher)->find($md, 'important', '', '');

    expect($result->confident)->toBeTrue()
        ->and(mb_substr($md, $result->start, $result->end - $result->start))->toBe('important');
});

it('matches text inside italic markers', function () {
    $md = 'This is _emphasised_ text.';
    $result = (new MarkdownSelectionMatcher)->find($md, 'emphasised', '', '');

    expect($result->confident)->toBeTrue()
        ->and(mb_substr($md, $result->start, $result->end - $result->start))->toBe('emphasised');
});

it('matches link visible text not the URL', function () {
    $md = 'See [click here](https://example.com) for more.';
    $result = (new MarkdownSelectionMatcher)->find($md, 'click here', '', '');

    expect($result->confident)->toBeTrue()
        ->and(mb_substr($md, $result->start, $result->end - $result->start))->toBe('click here');
});

it('matches heading text without the hash prefix', function () {
    $md = "# Learning Objectives\n\nSome content.";
    $result = (new MarkdownSelectionMatcher)->find($md, 'Learning Objectives', '', '');

    expect($result->confident)->toBeTrue()
        ->and(mb_substr($md, $result->start, $result->end - $result->start))->toBe('Learning Objectives');
});

it('matches list item text without the list marker', function () {
    $md = "Items:\n- First item\n- Second item";
    $result = (new MarkdownSelectionMatcher)->find($md, 'First item', '', '');

    expect($result->confident)->toBeTrue()
        ->and(mb_substr($md, $result->start, $result->end - $result->start))->toBe('First item');
});

it('resolves duplicate text using surrounding context', function () {
    // Use enough padding so the first occurrence's following window does NOT
    // contain the context string that uniquely follows the second occurrence.
    $padding = str_repeat('x', 200);
    $md = "Apple is mediocre.\n\n{$padding}\n\nApple is great.";
    $result = (new MarkdownSelectionMatcher)->find($md, 'Apple', '', 'is great.');

    expect($result->confident)->toBeTrue()
        ->and($result->start)->toBe(mb_strpos($md, 'Apple', 10)); // second occurrence
});

it('returns ambiguous when duplicate text cannot be resolved by context', function () {
    $md = "Apple.\n\nApple.";
    $result = (new MarkdownSelectionMatcher)->find($md, 'Apple', '', '');

    expect($result->confident)->toBeFalse();
});

it('returns ambiguous when text is not found in source', function () {
    $md = 'Hello world.';
    $result = (new MarkdownSelectionMatcher)->find($md, 'xyz not here', '', '');

    expect($result->confident)->toBeFalse();
});

it('matches inline code content without backticks', function () {
    $md = 'Run `php artisan migrate` to update.';
    $result = (new MarkdownSelectionMatcher)->find($md, 'php artisan migrate', '', '');

    expect($result->confident)->toBeTrue()
        ->and(mb_substr($md, $result->start, $result->end - $result->start))->toBe('php artisan migrate');
});

it('matches strikethrough text without tildes', function () {
    $md = 'This is ~~deleted~~ text.';
    $result = (new MarkdownSelectionMatcher)->find($md, 'deleted', '', '');

    expect($result->confident)->toBeTrue()
        ->and(mb_substr($md, $result->start, $result->end - $result->start))->toBe('deleted');
});

it('matches image alt text without markup', function () {
    $md = 'Here is ![a diagram](diagram.png) shown above.';
    $result = (new MarkdownSelectionMatcher)->find($md, 'a diagram', '', '');

    expect($result->confident)->toBeTrue()
        ->and(mb_substr($md, $result->start, $result->end - $result->start))->toBe('a diagram');
});

it('matches bold text using double-underscore delimiters', function () {
    $md = 'This is __critical__ information.';
    $result = (new MarkdownSelectionMatcher)->find($md, 'critical', '', '');

    expect($result->confident)->toBeTrue()
        ->and(mb_substr($md, $result->start, $result->end - $result->start))->toBe('critical');
});

it('matches text after a blockquote marker', function () {
    $md = "> This is a quoted passage.\n\nNormal text.";
    $result = (new MarkdownSelectionMatcher)->find($md, 'This is a quoted passage.', '', '');

    expect($result->confident)->toBeTrue()
        ->and(mb_substr($md, $result->start, $result->end - $result->start))->toBe('This is a quoted passage.');
});

it('matches ordered list item text without the numeric marker', function () {
    $md = "Steps:\n1. First step\n2. Second step";
    $result = (new MarkdownSelectionMatcher)->find($md, 'Second step', '', '');

    expect($result->confident)->toBeTrue()
        ->and(mb_substr($md, $result->start, $result->end - $result->start))->toBe('Second step');
});

it('handles an empty markdown string gracefully', function () {
    $result = (new MarkdownSelectionMatcher)->find('', 'anything', '', '');

    expect($result->confident)->toBeFalse();
});
