<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Your Business — Customer Portal</title>
    <style>
        :root {
            --color-surface:    #fff;
            --color-border:     #e0e0e0;
            --color-text:       #111;
            --color-text-muted: #666;
            --color-danger:     #dc2626;
            --color-primary:    #000;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #dcdcdc;
            font-family: Georgia, 'Times New Roman', serif;
            color: var(--color-text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 10px;
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }
        .login-logo { text-align: center; margin-bottom: 0.5rem; }
        .login-logo span { font-size: 2rem; font-weight: bold; }
        .login-title { text-align: center; font-size: 1.15rem; font-weight: bold; margin-bottom: 0.4rem; }
        .login-subtitle { text-align: center; color: var(--color-text-muted); font-size: 0.9rem; margin-bottom: 1.75rem; }
        .alert { padding: 0.7rem 1rem; border-radius: 5px; margin-bottom: 1.2rem; font-size: 0.88rem; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .business-list { display: flex; flex-direction: column; gap: 0.6rem; }
        .business-option {
            width: 100%;
            display: block;
            text-align: left;
            padding: 0.85rem 1rem;
            border: 1px solid var(--color-border);
            border-radius: 6px;
            background: #fafafa;
            font: inherit;
            font-size: 0.95rem;
            color: var(--color-text);
            cursor: pointer;
        }
        .business-option:hover { background: #f0f0f0; border-color: #bbb; }
        .business-option .name { font-weight: bold; }
        .business-option .hint { display: block; font-size: 0.78rem; color: var(--color-text-muted); margin-top: 2px; }
        .login-footer { text-align: center; margin-top: 1.4rem; font-size: 0.85rem; color: var(--color-text-muted); }
        .login-footer a { color: var(--color-primary); }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-logo"><span>&#128100;</span></div>
    <div class="login-title">Choose your business</div>
    <p class="login-subtitle">Your email is registered with more than one business. Select the one you want to sign in to.</p>

    @if($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <div class="business-list">
        @foreach($customers as $customer)
            <form method="POST" action="{{ route('portal.choose-business.post') }}">
                @csrf
                <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                <button type="submit" class="business-option">
                    <span class="name">{{ optional($customer->business)->name ?? 'Unknown business' }}</span>
                    <span class="hint">Signed in as {{ $customer->name }}</span>
                </button>
            </form>
        @endforeach
    </div>

    <div class="login-footer">
        <a href="{{ route('portal.login') }}">&larr; Back to sign in</a>
    </div>
</div>
</body>
</html>
