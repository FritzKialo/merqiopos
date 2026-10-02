<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied | Merqio POS</title>
    <link rel="stylesheet" href="{{ asset('fonts/phosphor/phosphor-bold.css') }}">
    <link rel="stylesheet" href="{{ asset('css/main.css') }}">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .error-wrap { text-align: center; padding: 2rem; max-width: 420px; }
        .error-num { font-family: var(--font-heading); font-size: 6rem; font-weight: 800; color: var(--color-danger); line-height: 1; margin-bottom: 0.5rem; }
        .error-title { font-family: var(--font-heading); font-size: var(--text-2xl); font-weight: 700; margin-bottom: 0.75rem; }
        .error-msg { color: var(--color-text-muted); font-size: var(--text-sm); line-height: 1.7; margin-bottom: 2rem; }
        .error-links { display: flex; align-items: center; justify-content: center; gap: 1.5rem; }
        .error-links a { font-size: var(--text-sm); color: var(--color-primary); font-weight: 600; }
        .error-links a:hover { text-decoration: underline; }
        .sep { color: var(--color-border-2); }
    </style>
</head>
<body>
    <div class="error-wrap">
        <div class="error-num">403</div>
        <div class="error-title">Access Denied</div>
        <p class="error-msg">You don't have permission to view this page. Contact your store owner if you believe this is a mistake.</p>
        <div class="error-links">
            @auth
                <a href="{{ route('dashboard') }}">Go to Dashboard</a>
                <span class="sep">|</span>
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dashboard') }}">Go Back</a>
            @else
                <a href="{{ route('login') }}">Sign In</a>
            @endauth
        </div>
    </div>
</body>
</html>
