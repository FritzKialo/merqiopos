<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Something went wrong | Merqio POS</title>
    <link rel="stylesheet" href="{{ asset('fonts/phosphor/phosphor-bold.css') }}">
    <link rel="stylesheet" href="{{ asset('css/main.css') }}">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .error-wrap { text-align: center; padding: 2rem; max-width: 460px; }
        .error-num { font-size: 5rem; font-weight: 800; opacity: .35; }
        .error-title { font-size: 1.5rem; font-weight: 700; margin-bottom: .5rem; }
        .error-msg { color: var(--color-text-muted); font-size: .9rem; line-height: 1.7; margin-bottom: 1.4rem; }
        .error-ref { display: inline-block; font-family: monospace; font-size: .9rem; padding: 6px 12px; border: 1px dashed var(--color-border-2, #888); border-radius: 6px; margin-bottom: 1.4rem; }
        .error-links { display: flex; align-items: center; justify-content: center; gap: 1.5rem; }
        .error-links a { font-size: .9rem; color: var(--color-primary); font-weight: 600; }
    </style>
</head>
<body>
    <div class="error-wrap">
        <div class="error-num">500</div>
        <div class="error-title">Something went wrong</div>
        <p class="error-msg">That was our mistake, not yours. Nothing you entered has been lost that was already saved. The problem has been recorded for our team. If you contact support, quote this reference so we can find it straight away:</p>
        @php $errorRef = request()->attributes->get('dev_request_id'); @endphp
        @if($errorRef)<div class="error-ref">Reference: {{ $errorRef }}</div>@endif
        <div class="error-links">
            <a href="javascript:history.back()">&larr; Go back</a>
            <a href="{{ url('/') }}">Home</a>
        </div>
    </div>
</body>
</html>
