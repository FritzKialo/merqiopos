<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Portal Login</title>
    <style>
        :root {
            --color-surface:    #fff;
            --color-border:     #e0e0e0;
            --color-text:       #111;
            --color-text-muted: #666;
            --color-danger:     #dc2626;
            /* Matches the invite email's brand color (portal/emails/invite.blade.php)
               so a customer who clicks through from that email lands somewhere
               that visually looks like the same product, not a different site. */
            --color-primary:    #4f46e5;
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
        .login-subtitle { text-align: center; color: var(--color-text-muted); font-size: 0.9rem; margin-bottom: 2rem; }
        .form-group { margin-bottom: 1.1rem; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: bold; margin-bottom: 0.3rem; }
        .form-group input {
            width: 100%;
            padding: 0.6rem 0.85rem;
            border: 1px solid var(--color-border);
            border-radius: 5px;
            font: inherit;
            font-size: 0.9rem;
        }
        .form-group input:focus { outline: 2px solid var(--color-primary); outline-offset: 2px; border-color: transparent; }
        .form-error { color: var(--color-danger); font-size: 0.82rem; margin-top: 0.25rem; }
        .btn-login {
            width: 100%;
            padding: 0.7rem;
            background: var(--color-primary);
            color: #fff;
            border: none;
            border-radius: 5px;
            font: inherit;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 0.5rem;
        }
        .btn-login:hover { opacity: 0.85; }
        .login-footer { text-align: center; margin-top: 1.2rem; font-size: 0.85rem; color: var(--color-text-muted); }
        .login-footer a { color: var(--color-primary); }
        .powered-by { text-align: center; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--color-border); font-size: 0.75rem; color: #94a3b8; }
        .powered-by strong { color: var(--color-primary); }
        .alert { padding: 0.7rem 1rem; border-radius: 5px; margin-bottom: 1.2rem; font-size: 0.88rem; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .remember-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; margin-bottom: 1rem; }
        .remember-row label { display: flex; align-items: center; gap: 0.4rem; font-weight: normal; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-logo"><span>&#128100;</span></div>
    <p class="login-subtitle">Customer Portal — sign in to your account</p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('portal.login.post') }}">
        @csrf
        <div class="form-group">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="remember-row">
            <label>
                <input type="checkbox" name="remember"> Remember me
            </label>
            <a href="{{ route('portal.forgot') }}">Forgot password?</a>
        </div>
        <button type="submit" class="btn-login">Sign in</button>
    </form>

    <div class="login-footer">
        Don't have access? Contact the business to invite you.
    </div>
    {{-- Matches the invite email's own "Powered by Merqio POS" footer line
    (portal/emails/invite.blade.php) — a customer clicking through from that
    email had nothing here connecting this page back to what invited them. --}}
    <div class="powered-by">Powered by <strong>Merqio POS</strong></div>
</div>
</body>
</html>
