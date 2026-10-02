<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribed — {{ $businessName }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; background: #eef1f5; color: #111; margin: 0; padding: 0; }
        .wrap { max-width: 480px; margin: 80px auto; background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 40px; text-align: center; }
        .wrap h1 { font-size: 1.3rem; margin-bottom: 12px; }
        .wrap p { color: #666; line-height: 1.6; }
        .icon { font-size: 2.2rem; margin-bottom: 12px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="icon">&#9989;</div>
    <h1>You've been unsubscribed</h1>
    <p>You won't receive any more newsletter emails from {{ $businessName }}. This doesn't affect invoices, receipts, or other account-related messages.</p>
</div>
</body>
</html>
