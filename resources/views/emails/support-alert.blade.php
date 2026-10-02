<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Support message</title></head>
<body style="font-family:Arial,Helvetica,sans-serif;background:#f5f5f5;margin:0;padding:24px;color:#222;">
<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:8px;padding:28px;border:1px solid #e5e5e5;">
    <h2 style="margin:0 0 6px;font-size:18px;">New support message</h2>
    <p style="margin:0 0 16px;color:#666;font-size:14px;">
        <strong>{{ $conversation->user?->name ?? 'A user' }}</strong>
        ({{ $conversation->role ?? 'user' }}) at <strong>{{ $conversation->business?->name ?? 'a store' }}</strong>
    </p>
    <div style="background:#f8fafc;border-left:4px solid #6366f1;padding:12px 14px;border-radius:4px;font-size:15px;white-space:pre-line;">{{ $preview }}</div>
    @if($conversation->last_page)
    <p style="font-size:12px;color:#888;margin:14px 0 0;">They were on: {{ $conversation->last_page }}</p>
    @endif
    <p style="margin:22px 0 0;">
        <a href="{{ route('admin.support.show', $conversation) }}" style="background:#6366f1;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;font-size:14px;">Open the conversation</a>
    </p>
    <p style="font-size:11px;color:#aaa;margin:20px 0 0;">You get at most one email per conversation every {{ config('support.alert_cooldown_minutes') }} minutes.</p>
</div>
</body>
</html>
