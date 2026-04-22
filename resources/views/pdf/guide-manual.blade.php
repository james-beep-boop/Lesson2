<!DOCTYPE html>
<html lang="{{ $language === 'sw' ? 'sw' : 'en' }}">
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
    .section {
        margin-bottom: 16px;
        page-break-inside: avoid;
    }
    .section h2 {
        font-size: 14px;
        color: #1e40af;
        margin: 0 0 8px;
        border-bottom: 1px solid #dbeafe;
        padding-bottom: 4px;
    }
    .content {
        line-height: 1.6;
    }
    p {
        margin: 0 0 8px;
    }
    ul, ol {
        margin: 0 0 8px 20px;
        padding: 0;
    }
    li {
        margin-bottom: 4px;
    }
    code {
        background: #f3f4f6;
        padding: 1px 4px;
        border-radius: 3px;
        font-size: 11px;
        font-family: monospace;
    }
    strong {
        color: #111827;
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
    <h1>{{ $title }}</h1>
</div>

@foreach($sections as $section)
    <div class="section">
        <h2>{{ $section['title'] }}</h2>
        <div class="content">
            {!! (new \League\CommonMark\GithubFlavoredMarkdownConverter(['html_input' => 'strip']))->convert($section['body'] ?? '') !!}
        </div>
    </div>
@endforeach

<div class="footer">
    @include('pdf.partials.license-footer', [
        'exportedAt' => $exportedAt,
        'exportLabel' => ($language ?? 'en') === 'sw' ? 'Imetolewa' : 'Exported',
    ])
</div>
</body>
</html>
