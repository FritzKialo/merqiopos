<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Error spike</title></head>
<body style="font-family:Arial,Helvetica,sans-serif;background:#f5f5f5;margin:0;padding:24px;color:#222;">
<div style="max-width:640px;margin:0 auto;background:#fff;border-radius:8px;padding:28px;border:1px solid #e5e5e5;">
    <h2 style="margin:0 0 6px;font-size:18px;">
        {{ $errors->count() === 1 ? 'An error is recurring' : $errors->count() . ' errors are recurring' }}
    </h2>
    <p style="margin:0 0 20px;color:#666;font-size:14px;">
        Each of these has happened at least {{ config('security.error_alert_threshold') }} more times
        in the last {{ config('security.error_alert_window_minutes') }} minutes than the last time you were alerted about it.
    </p>

    @foreach($errors as $error)
    <div style="background:#fef2f2;border-left:4px solid #ef4444;padding:14px 16px;border-radius:4px;margin-bottom:14px;">
        <p style="margin:0 0 4px;font-size:14px;font-weight:bold;">{{ $error->exception }}</p>
        <p style="margin:0 0 8px;font-size:13px;color:#555;white-space:pre-line;">{{ $error->message }}</p>
        <p style="margin:0;font-size:12px;color:#888;">
            {{ $error->method }} {{ $error->path }}
            @if($error->business) &middot; {{ $error->business->name }} @endif
            &middot; seen {{ $error->count }} times total &middot; last {{ $error->last_seen_at?->diffForHumans() }}
        </p>
    </div>
    @endforeach

    <p style="margin:22px 0 0;">
        <a href="{{ route('admin.logs.errors') }}" style="background:#ef4444;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;font-size:14px;">Open Developer Logs</a>
    </p>
</div>
</body>
</html>
