<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Required | Merqio POS</title>

    <!-- Fonts & Icons (self-hosted) -->
    <link rel="stylesheet" href="{{ asset('fonts/phosphor/phosphor-bold.css') }}">

    <!-- Styles -->
    <link rel="stylesheet" href="{{ asset('css/main.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}">

    <style>
        html { scroll-behavior: smooth; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-6);
            background: var(--color-background);
        }

        .wall {
            width: 100%;
            max-width: 520px;
            text-align: center;
        }

        .wall-icon {
            width: 80px;
            height: 80px;
            background: var(--color-danger-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--space-6);
            font-size: 2.2rem;
            color: var(--color-danger);
        }

        .wall-title {
            font-family: var(--font-heading);
            font-size: var(--text-3xl);
            font-weight: 800;
            color: var(--color-text);
            letter-spacing: -0.03em;
            margin-bottom: var(--space-3);
        }

        .wall-subtitle {
            font-size: var(--text-base);
            color: var(--color-text-muted);
            line-height: 1.65;
            margin-bottom: var(--space-8);
        }

        .wall-card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            padding: var(--space-8);
            margin-bottom: var(--space-6);
            box-shadow: var(--shadow-lg);
        }

        .wall-status {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            background: var(--color-danger-light);
            color: var(--color-danger);
            border-radius: var(--radius-full);
            padding: 0.35rem 1rem;
            font-size: var(--text-xs);
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: var(--space-6);
        }

        .wall-actions {
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
        }

        .wall-actions .btn {
            width: 100%;
            padding: 0.85rem;
            font-size: var(--text-base);
            justify-content: center;
        }

        .wall-plans {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--space-3);
            margin-bottom: var(--space-6);
            text-align: left;
        }

        .wall-plan {
            display: block;
            background: var(--color-surface-2);
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            cursor: pointer;
            transition: border-color 0.15s, transform 0.15s;
        }

        .wall-plan:hover {
            border-color: var(--color-primary);
            transform: translateY(-2px);
        }

        .wall-plan-name {
            font-size: var(--text-xs);
            font-weight: 700;
            color: var(--color-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: var(--space-1);
        }

        .wall-plan-price {
            font-family: var(--font-heading);
            font-size: var(--text-xl);
            font-weight: 800;
            color: var(--color-text);
        }

        .wall-plan-price span {
            font-size: var(--text-xs);
            font-weight: 400;
            color: var(--color-text-muted);
        }

        .wall-logout {
            font-size: var(--text-sm);
            color: var(--color-text-muted);
            margin-top: var(--space-4);
        }

        .wall-logout a {
            color: var(--color-text-muted);
            text-decoration: underline;
            text-underline-offset: 3px;
            transition: color 0.15s;
        }

        .wall-logout a:hover { color: var(--color-text); }

        /* Logo */
        .wall-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2);
            margin-bottom: var(--space-8);
        }

        .wall-logo-icon {
            width: 40px;
            height: 44px;
            background: url("/images/merqio-mark.svg") center / contain no-repeat;
        }

        .wall-logo-text {
            font-family: var(--font-heading);
            font-size: var(--text-lg);
            font-weight: 800;
            color: var(--color-text);
            letter-spacing: -0.02em;
        }

        .wall-logo-text span {
            color: var(--color-text-muted);
            font-weight: 500;
        }

        @media (max-width: 480px) {
            .wall-plans { grid-template-columns: 1fr; }
        }
    </style>
    @include('layouts.partials.brand-icons')
</head>
<body>

<div class="wall">

    {{-- Logo --}}
    <div class="wall-logo">
        <div class="wall-logo-icon">
            
        </div>
        <div class="wall-logo-text">Merqio<span>POS</span></div>
    </div>

    {{-- Flash warning --}}
    @if(session('warning'))
        <div class="alert alert-warning" style="margin-bottom: var(--space-5); text-align:left;">
            
            {{ session('warning') }}
        </div>
    @endif

    <div class="wall-card">

        @php $org = Auth::user()->organization; @endphp

        <div class="wall-status">
            
            @if($org?->status === 'trial')
                Trial Expired
            @else
                Subscription Expired
            @endif
        </div>

        <h1 class="wall-title">Access Paused</h1>
        <p class="wall-subtitle">
            Your
            @if($org?->status === 'trial')
                1-month free trial has ended.
            @else
                subscription has expired.
            @endif
            Choose a plan to continue managing your stores with Merqio POS.
        </p>

        @if(Auth::user()->hasAnyRole('owner', 'manager'))

            {{-- Plan options — pulled live from config/plans.php rather than
            hardcoded here a second time (this exact page previously drifted
            out of sync with the real prices/names after a plan restructure —
            "Scale" no longer exists at all, and Solo/Growth's prices had both
            changed). --}}
            <div class="wall-plans">
                @foreach(config('plans.org') as $key => $plan)
                <a href="{{ route('settings.subscription') }}#plan-{{ $key }}" class="wall-plan" @if($key === 'growth') style="border-color: var(--color-primary);" @endif>
                    <div class="wall-plan-name" @if($key === 'growth') style="color:var(--color-primary);" @endif>{{ $plan['name'] }}</div>
                    <div class="wall-plan-price">KSh {{ number_format($plan['price'], 0) }}<span>/mo</span></div>
                </a>
                @endforeach
            </div>

            <div class="wall-actions">
                <a href="{{ route('settings.subscription') }}" class="btn btn-primary">

                    Choose a Plan & Renew
                </a>
            </div>

        @else

            <p class="wall-subtitle" style="margin-top: -8px;">
                Only the business owner or a manager can renew the subscription.
                Please contact your business owner to choose a plan and restore access.
            </p>

        @endif

    </div>

    <div class="wall-logout">
        Not you?
        <form method="POST" action="{{ route('logout') }}" style="display:inline;">
            @csrf
            <button type="submit"
                    style="background:none;border:none;padding:0;cursor:pointer;
                           color:var(--color-text-muted);text-decoration:underline;
                           text-underline-offset:3px;font-size:var(--text-sm);">
                Sign out
            </button>
        </form>
    </div>

</div>

</body>
</html>
