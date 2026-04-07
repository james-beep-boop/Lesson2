<?php

use App\Services\MarkdownNormalizer;

test('normalizes CRLF to LF', function () {
    $normalizer = new MarkdownNormalizer;
    $result = $normalizer->normalize("# Heading\r\nSome text\r\nMore text");
    expect($result)->toBe("# Heading\nSome text\nMore text\n");
});

test('normalizes lone CR to LF', function () {
    $normalizer = new MarkdownNormalizer;
    $result = $normalizer->normalize("# Heading\rSome text\rMore text");
    expect($result)->toBe("# Heading\nSome text\nMore text\n");
});

test('normalizes a string containing mixed CRLF and lone CR in one pass', function () {
    $normalizer = new MarkdownNormalizer;
    $result = $normalizer->normalize("Line one\r\nLine two\rLine three");
    expect($result)->toBe("Line one\nLine two\nLine three\n");
});

test('leaves LF-only content unchanged apart from trailing newline', function () {
    $normalizer = new MarkdownNormalizer;
    $result = $normalizer->normalize("# Heading\nSome text\n");
    expect($result)->toBe("# Heading\nSome text\n");
});

test('appends trailing newline to non-empty content that lacks one', function () {
    $normalizer = new MarkdownNormalizer;
    $result = $normalizer->normalize("No trailing newline");
    expect($result)->toBe("No trailing newline\n");
});

test('does not double-append trailing newline if already present', function () {
    $normalizer = new MarkdownNormalizer;
    $result = $normalizer->normalize("Already has newline\n");
    expect($result)->toBe("Already has newline\n");
});

test('returns empty string unchanged — does not append newline to empty string', function () {
    $normalizer = new MarkdownNormalizer;
    expect($normalizer->normalize(''))->toBe('');
});

test('does not alter headings, bullets, bold, italic, or table syntax', function () {
    $normalizer = new MarkdownNormalizer;
    $input = "# Heading\n\n- item one\n- item two\n\n**bold** and *italic*\n\n| A | B |\n|---|---|\n| 1 | 2 |\n";
    expect($normalizer->normalize($input))->toBe($input);
});

test('is idempotent — normalizing twice produces same result as normalizing once', function () {
    $normalizer = new MarkdownNormalizer;
    $input = "Mixed\r\nline\rendings no final newline";
    $once = $normalizer->normalize($input);
    $twice = $normalizer->normalize($once);
    expect($twice)->toBe($once);
});
