<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Unsubscribe — {{ $businessName }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; background: #eef1f5; color: #111; margin: 0; padding: 0; }
        .wrap { max-width: 480px; margin: 80px auto; background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 40px; text-align: center; }
        .wrap h1 { font-size: 1.3rem; margin-bottom: 12px; }
        .wrap p { color: #666; line-height: 1.6; }
        button { margin-top: 18px; background: #111; color: #fff; border: 0; border-radius: 6px; padding: 12px 24px; font-size: 1rem; cursor: pointer; }
    </style>
</head>
<body>
<div class="wrap">
    @if($alreadyDone)
        <h1>You're already unsubscribed</h1>
        <p>You won't receive newsletter or campaign emails from {{ $businessName }}.</p>
    @else
        <h1>Unsubscribe from {{ $businessName }}?</h1>
        <p>You will stop receiving newsletter and campaign emails from {{ $businessName }}. Invoices, receipts and other account messages are not affected.</p>
        <form method="POST" action="{{ $confirmUrl }}">
            @csrf
            <button type="submit">Yes, unsubscribe me</button>
        </form>
    @endif
</div>
</body>
</html>
