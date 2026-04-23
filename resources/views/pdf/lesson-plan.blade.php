<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    body {
        font-family: DejaVu Sans, Arial, sans-serif;
        font-size: 12px;
        color: #1a1a1a;
        margin: 0;
        padding: 0 0 54px;
    }
    .header {
        border-bottom: 2px solid #2563eb;
        padding-bottom: 10px;
        margin-bottom: 18px;
    }
    .header h1 {
        font-size: 18px;
        margin: 0 0 4px;
        color: #1e40af;
    }
    .meta {
        font-size: 10px;
        color: #555;
        margin-bottom: 4px;
    }
    .meta strong { color: #222; }
    .official-badge {
        display: inline-block;
        background: #dcfce7;
        color: #166534;
        font-size: 10px;
        font-weight: bold;
        padding: 2px 7px;
        border-radius: 4px;
        margin-top: 4px;
    }
    .content {
        line-height: 1.6;
    }
    h1, h2, h3, h4 {
        color: #1e40af;
        margin-top: 16px;
        margin-bottom: 6px;
    }
    h1 { font-size: 16px; }
    h2 { font-size: 14px; }
    h3 { font-size: 12px; }
    p  { margin: 0 0 8px; }
    ul, ol { margin: 0 0 8px 20px; padding: 0; }
    li { margin-bottom: 3px; }
    code {
        background: #f3f4f6;
        padding: 1px 4px;
        border-radius: 3px;
        font-size: 11px;
        font-family: monospace;
    }
    pre {
        background: #f3f4f6;
        padding: 8px;
        border-radius: 4px;
        font-size: 10px;
        overflow: auto;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
        font-size: 11px;
    }
    th {
        background-color: #dbeafe;
        color: #1e40af;
        font-weight: bold;
        border: 1px solid #93c5fd;
        padding: 5px 8px;
        text-align: left;
    }
    td {
        border: 1px solid #d1d5db;
        padding: 5px 8px;
        vertical-align: top;
    }
    .footer {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        border-top: 1px solid #e5e7eb;
        padding-top: 8px;
        font-size: 7px;
        line-height: 1.3;
        color: #9ca3af;
        text-align: center;
    }
</style>
</head>
<body>

<div class="header">
    <h1>{{ $family->subjectGrade->subject->name }} — Grade {{ $family->subjectGrade->grade }} · Day {{ $family->day }}</h1>
    <div class="meta">
        <strong>Version:</strong> v{{ $version->version }} &nbsp;|&nbsp;
        <strong>Contributor:</strong> {{ $version->contributor->name ?? '—' }} &nbsp;|&nbsp;
        <strong>Date:</strong> {{ $version->created_at->format('d M Y') }}
    </div>
    @if($version->revision_note)
        <div class="meta"><strong>Revision note:</strong> {{ $version->revision_note }}</div>
    @endif
    @if($family->official_version_id === $version->id)
        <span class="official-badge">✓ Official Version</span>
    @endif
</div>

<div class="content">
    {!! (new \League\CommonMark\GithubFlavoredMarkdownConverter(['html_input' => 'strip']))->convert($version->content ?? '') !!}
</div>

<div class="footer">
    @include('pdf.partials.license-footer', ['exportedAt' => $exportedAt])
</div>

</body>
</html>
