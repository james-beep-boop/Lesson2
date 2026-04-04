<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #1a1a1a; max-width: 600px; margin: 0 auto; padding: 20px;">
<h2 style="color:#1e40af;">Lesson Plan Attached</h2>

<p>
    <strong>{{ $version->family->subjectGrade->subject->name ?? '' }} — Grade {{ $version->family->subjectGrade->grade ?? '' }}, Day {{ $version->family->day ?? '' }} · v{{ $version->version }}</strong>
</p>

<p>Sent by: <strong>{{ $senderName }}</strong></p>

@if($customMessage)
<div style="background:#f9fafb;border-left:4px solid #2563eb;padding:10px 14px;margin:16px 0;border-radius:4px;">
    {{ $customMessage }}
</div>
@endif

<p>Please find the lesson plan attached as a PDF.</p>

<p style="font-size:11px;color:#6b7280;margin-top:24px;border-top:1px solid #e5e7eb;padding-top:12px;">
    This message was sent from the ARES Kenya Lesson Repository.
</p>
</body>
</html>
