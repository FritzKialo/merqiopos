<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Completed</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; color: #334155; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #ffffff; padding: 28px 40px 22px; text-align: center; border-bottom: 1px solid #e2e8f0; }
        .header h1 { margin: 0; font-size: 22px; color: #0f766e; }
        .header p { margin: 6px 0 0; font-size: 14px; color: #64748b; }
        .body { padding: 40px; line-height: 1.7; font-size: 14px; }
        .file-list { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px 20px; margin: 20px 0; }
        .file-list p { margin: 4px 0; font-family: monospace; font-size: 13px; }
        .note { background: #f0fdfa; border-left: 4px solid #0f766e; padding: 16px 20px; border-radius: 4px; margin: 20px 0; font-size: 13px; }
        .footer { background: #f8fafc; padding: 24px 40px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
<div class="wrapper">
    {{-- Inline background/color duplicate the .header CSS above — Gmail
    (dark mode, clipped/long-email view) can strip or override a <style>-block
    rule, which would otherwise leave this heading text white on white. --}}
    <div class="header" style="background:#ffffff !important; padding:28px 40px 22px !important; text-align:center !important; border-bottom:1px solid #e2e8f0 !important;">
        <h1 style="margin:0; color:#0f766e !important;">💾 Backup Completed</h1>
        <p style="margin:6px 0 0; color:#64748b !important;">{{ $ranAt->format('l, d F Y \a\t H:i') }}</p>
    </div>
    <div class="body">
        <p>Today's automatic backup of Merqio POS's database and uploaded files completed successfully. A copy is attached to this email, and another copy is stored on the server (kept for 14 days).</p>

        <div class="file-list">
            @foreach($files as $file)
            <p>📎 {{ basename($file) }} ({{ number_format(filesize($file) / 1048576, 2) }} MB)</p>
            @endforeach
        </div>

        <div class="note">
            <strong>Note:</strong> this backup does not include the <code>.env</code> file (API keys, database password, mail credentials) — those stay on the server only, for security. If the server itself is ever lost, those need restoring separately.
        </div>
    </div>
    <div class="footer">
        <p>Merqio POS — automated backup system</p>
    </div>
</div>
</body>
</html>
