<?php

use App\Services\MarkdownSelectionMatcher;

beforeEach(function () {
    $this->matcher = new MarkdownSelectionMatcher;
});

it('returns ambiguous for empty selection', function () {
    $result = $this->matcher->find('# Hello world', '   ');
    expect($result->confident)->toBeFalse();
});

it('returns ambiguous when text not found in markdown', function () {
    $result = $this->matcher->find('# Hello world', 'something else entirely');
    expect($result->confident)->toBeFalse();
});

it('returns confident with correct offsets for unique text', function () {
    $markdown = "# Title\n\nThis is a paragraph with some unique content here.";
    $result = $this->matcher->find($markdown, 'unique content');

    expect($result->confident)->toBeTrue();
    expect(substr($markdown, $result->start, $result->end - $result->start))->toBe('unique content');
});

it('returns confident for text at start of markdown', function () {
    $markdown = 'Hello world and more text';
    $result = $this->matcher->find($markdown, 'Hello world');

    expect($result->confident)->toBeTrue();
    expect($result->start)->toBe(0);
    expect($result->end)->toBe(11);
});

it('returns confident for text at end of markdown', function () {
    $markdown = 'Some text and then the end';
    $result = $this->matcher->find($markdown, 'the end');

    expect($result->confident)->toBeTrue();
    expect($result->end)->toBe(strlen($markdown));
});

it('returns ambiguous when duplicates exist and no context provided', function () {
    $markdown = "apple\n\nsome filler text\n\napple";
    $result = $this->matcher->find($markdown, 'apple');

    expect($result->confident)->toBeFalse();
});

it('resolves duplicate using context before', function () {
    // Long enough that the 120-char window before the second "apple" clearly contains the unique marker.
    $padding = str_repeat('x', 200);
    $markdown = "apple first occurrence\n\n{$padding}\n\nonly before second apple\n\napple second occurrence";
    $result = $this->matcher->find($markdown, 'apple', 'only before second apple', '');

    expect($result->confident)->toBeTrue();
    // Second "apple" starts well into the string.
    expect($result->start)->toBeGreaterThan(50);
});

it('resolves duplicate using context after', function () {
    $padding = str_repeat('x', 200);
    $markdown = "apple first occurrence\n\n{$padding}\n\napple second occurrence only here";
    $result = $this->matcher->find($markdown, 'apple', '', 'second occurrence only here');

    expect($result->confident)->toBeTrue();
    expect(substr($markdown, $result->start, $result->end - $result->start))->toBe('apple');
    expect($result->start)->toBeGreaterThan(50);
});

it('remains ambiguous when both duplicates match equally well', function () {
    // Identical context around both occurrences — algorithm cannot distinguish them.
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
    expect(substr($markdown, $result->start, $result->end - $result->start))->toBe("First line\nSecond line");
});

it('trims whitespace from selected text before matching', function () {
    $markdown = 'The quick brown fox jumps';
    $result = $this->matcher->find($markdown, '  quick brown  ');

    expect($result->confident)->toBeTrue();
    expect(substr($markdown, $result->start, $result->end - $result->start))->toBe('quick brown');
});
