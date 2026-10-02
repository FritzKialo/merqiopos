<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You're Offline — Merqio POS</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: Georgia, 'Times New Roman', serif; background: #dcdcdc; color: #1a1a1a; display: flex; align-items: center; justify-content: center; min-height: 100vh; text-align: center; padding: 24px; }
        .box { background: #fff; border-radius: 12px; padding: 48px 40px; max-width: 420px; }
        .icon { margin-bottom: 16px; }
        .icon svg { width: 56px; height: 56px; color: #1a1a1a; }
        h1 { font-size: 1.6rem; margin: 0 0 12px; }
        p { color: #666; margin: 0 0 24px; line-height: 1.6; }
        button { background: #000; color: #fff; border: none; padding: 12px 28px; border-radius: 6px; font-family: Georgia, serif; font-size: 1rem; cursor: pointer; }
        button:hover { background: #333; }
    </style>
    @include('layouts.partials.brand-icons')
</head>
<body>
    <div class="box">
        <div class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="1" y1="1" x2="23" y2="23"/><path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/><path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/><path d="M10.71 5.05A16 16 0 0 1 22.58 9"/><path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
        </div>
        <h1>You're Offline</h1>
        <p>Merqio POS needs an internet connection for most features. Please check your connection and try again.</p>
        <button onclick="window.location.reload()">Try Again</button>
    </div>
</body>
</html>
