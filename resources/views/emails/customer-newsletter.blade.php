<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #ffffff; padding: 28px 40px 22px; text-align: center; border-bottom: 1px solid #e2e8f0; }
        .header h1 { margin: 0; font-size: 22px; color: #18181b; }
        .body { padding: 40px; color: #334155; line-height: 1.7; }
        .body p { white-space: pre-line; }
        .footer { background: #f8fafc; padding: 24px 40px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
        .footer a { color: #64748b; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header" style="background:#ffffff !important; padding:28px 40px 22px !important; text-align:center !important; border-bottom:1px solid #e2e8f0 !important;">
        <h1 style="margin:0; color:#18181b !important;">📣 {{ $businessName }}</h1>
    </div>
    <div class="body">
        {{-- Campaign messages already address the customer through {name}. --}}
        @if($showGreeting ?? true)
        <p>Hi {{ $customerName }},</p>
        @endif
        <p>{{ $body }}</p>
    </div>
    <div class="footer">
        <p>&copy; {{ date('Y') }} {{ $businessName }}</p>
        <p>You're receiving this because you're a customer of {{ $businessName }}.
           <a href="{{ $unsubscribeUrl }}">Unsubscribe from these emails</a>.</p>
    </div>
</div>
</body>
</html>
