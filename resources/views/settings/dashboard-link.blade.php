@extends('layouts.app')
@section('title', 'Manager Dashboard Link')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">Manage your business and account</p>
    </div>
</div>

<div class="settings-layout">

    @include('settings._nav')

    <div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if(session('dashboard_token_plain'))
        <div class="alert alert-warning" style="border:2px solid var(--color-warning,#f59e0b);">
            <div style="display:flex;align-items:flex-start;gap:.75rem;">
                <div style="flex:1;">
                    <strong>Copy this link now — it will not be shown again.</strong>
                    <div style="margin-top:.75rem;display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
                        <code id="plain-link" style="background:var(--color-surface-2);padding:.5rem .75rem;border-radius:6px;font-size:.8rem;word-break:break-all;flex:1;">{{ url('/view/' . session('dashboard_token_plain')) }}</code>
                        <button type="button" onclick="copyLink()" class="btn btn-outline btn-sm">
                            Copy
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="settings-card">
            <div class="settings-card-header">
                <h2 class="settings-card-title">
                    Manager Dashboard Link
                </h2>
            </div>
            <div class="settings-card-body">
                <p style="color:var(--color-text-muted);margin-bottom:1.5rem;">
                    Share this link with a manager (or open it yourself on your phone) to see today's sales and stock levels
                    at a glance — <strong>no login required</strong>. Anyone with the link can view this page; it cannot be
                    used to change anything.
                </p>

                @if($business->dashboard_token)
                <div style="display:flex;align-items:center;gap:.75rem;padding:.75rem;background:var(--color-surface-2);border-radius:8px;margin-bottom:1.5rem;">
                    <div>
                        <div style="font-weight:600;">Link Active</div>
                        <div style="font-size:.83rem;color:var(--color-text-muted);">A dashboard link is set. For security, it is not displayed again after generation.</div>
                    </div>
                </div>
                <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
                    <form method="POST" action="{{ route('settings.dashboard-link.generate') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline"
                            onclick="return confirm('This will invalidate the current link. Anyone using it will lose access. Continue?')">
                            Regenerate Link
                        </button>
                    </form>
                    <form method="POST" action="{{ route('settings.dashboard-link.revoke') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger"
                            onclick="return confirm('Revoke the dashboard link? It will stop working immediately.')">
                            Revoke Link
                        </button>
                    </form>
                </div>
                @else
                <p style="color:var(--color-text-muted);margin-bottom:1.5rem;">No dashboard link has been generated yet.</p>
                <form method="POST" action="{{ route('settings.dashboard-link.generate') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        Generate Dashboard Link
                    </button>
                </form>
                @endif
            </div>
        </div>

        <div class="settings-card" style="margin-top:1.5rem;">
            <div class="settings-card-header">
                <h2 class="settings-card-title">What the link shows</h2>
            </div>
            <div class="settings-card-body">
                <ul style="color:var(--color-text-muted);margin:0;padding-left:1.25rem;line-height:1.9;">
                    <li>Today's total sales and transaction count</li>
                    <li>The 15 most recent completed sales</li>
                    <li>Your product list with current stock levels</li>
                    <li>A low-stock warning list</li>
                </ul>
                <p style="color:var(--color-text-muted);margin-top:1rem;">
                    It's view-only — there is no way to create, edit, or delete anything from this link, unlike your
                    <a href="{{ route('settings.api') }}">API token</a>. Anyone who has the link can view it without
                    signing in, so treat it like a password: don't post it publicly, and regenerate it if you think
                    the wrong person has seen it.
                </p>
            </div>
        </div>

    </div>
</div>
</div>{{-- end .page --}}

@push('scripts')
<script>
function copyLink() {
    const text = document.getElementById('plain-link').textContent.trim();
    navigator.clipboard.writeText(text).then(() => {
        const btn = event.target.closest('button');
        const orig = btn.innerHTML;
        btn.innerHTML = 'Copied!';
        setTimeout(() => btn.innerHTML = orig, 2000);
    });
}
</script>
@endpush
@endsection
