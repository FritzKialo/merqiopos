<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Contact Form Submission</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #ffffff; padding: 24px 40px; border-bottom: 1px solid #e2e8f0; }
        .header h1 { color: #1f4a37; }
        .header p { color: #64748b; }
        .header h1 { margin: 0; font-size: 20px; }
        .header p { margin: 6px 0 0; opacity: 0.8; font-size: 13px; }
        .body { padding: 32px 40px; color: #334155; line-height: 1.7; }
        .details { width: 100%; border-collapse: collapse; font-size: 14px; margin-bottom: 20px; }
        .details td { padding: 6px 0; vertical-align: top; }
        .details td:first-child { color: #64748b; width: 110px; }
        .message-box { background: #f8f7f2; border: 1px solid #e2dccd; border-radius: 6px; padding: 16px 20px; font-size: 14px; white-space: pre-wrap; }
        .footer { background: #f8fafc; padding: 20px 40px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
<div class="wrapper">
    {{-- Inline background/color duplicate the .header CSS above — Gmail
    (dark mode, clipped/long-email view) can strip or override a <style>-block
    rule, which would otherwise leave this heading text white on white. --}}
    <div class="header" style="background:#ffffff !important; padding:24px 40px !important; border-bottom:1px solid #e2e8f0 !important;">
        <h1 style="margin:0; color:#1f4a37 !important;">✉️ New Contact Form Submission</h1>
        <p style="margin:6px 0 0; color:#64748b !important;">Received via merqiopos.com/contact</p>
    </div>
    <div class="body">
        <table class="details">
            <tr><td>Name</td><td>{{ $enquiry['name'] }}</td></tr>
            <tr><td>Email</td><td><a href="mailto:{{ $enquiry['email'] }}">{{ $enquiry['email'] }}</a></td></tr>
            @if(!empty($enquiry['phone']))
            <tr><td>Phone</td><td>{{ $enquiry['phone'] }}</td></tr>
            @endif
            <tr><td>Topic</td><td>{{ ucfirst($enquiry['subject']) }}</td></tr>
        </table>
        <div class="message-box">{{ $enquiry['message'] }}</div>
    </div>
    <div class="footer">
        Reply directly to this email to respond to {{ $enquiry['name'] }}.
    </div>
</div>
</body>
</html>
