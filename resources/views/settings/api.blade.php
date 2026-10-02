@extends('layouts.app')
@section('title', 'API Access')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1' }}">
@endpush

@section('content')
{{-- 'api_access' is an org-level feature (config/plans.php 'org' =>
[...]), not a key in any store-level plan config, so $business->
hasFeature('api_access') alone was always false — this whole page told
every Enterprise-plan owner they needed to upgrade to Enterprise. Check
the organization first, same resolution CheckPlanFeature/ApiTokenAuth use. --}}
@php $hasApiAccess = $business->organization?->hasFeature('api_access') ?? $business->hasFeature('api_access'); @endphp
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

        @if(session('api_token_plain'))
        <div class="alert alert-warning" style="border:2px solid var(--color-warning,#f59e0b);">
            <div style="display:flex;align-items:flex-start;gap:.75rem;">
                <div style="flex:1;">
                    <strong>Copy your API token now — it will not be shown again.</strong>
                    <div style="margin-top:.75rem;display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
                        <code id="plain-token" style="background:var(--color-surface-2);padding:.5rem .75rem;border-radius:6px;font-size:.85rem;word-break:break-all;flex:1;">{{ session('api_token_plain') }}</code>
                        <button type="button" onclick="copyToken()" class="btn btn-outline btn-sm">
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
                    API Token
                </h2>
            </div>
            <div class="settings-card-body">
                @if(!$hasApiAccess)
                <div style="text-align:center;padding:2rem 0;">
                    <p style="margin-top:.75rem;color:var(--color-text-muted);">API access requires the <strong>Enterprise plan</strong>.</p>
                    <a href="{{ route('settings.subscription') }}" class="btn btn-primary" style="margin-top:1rem;">Upgrade to Enterprise</a>
                </div>
                @elseif($business->api_token)
                <div style="display:flex;align-items:center;gap:.75rem;padding:.75rem;background:var(--color-surface-2);border-radius:8px;margin-bottom:1.5rem;">
                    <div>
                        <div style="font-weight:600;">Token Active</div>
                        <div style="font-size:.83rem;color:var(--color-text-muted);">A token is set. For security, the token value is not displayed after generation.</div>
                    </div>
                </div>
                <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
                    <form method="POST" action="{{ route('settings.api.generate') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline"
                            onclick="return confirm('This will invalidate your current token. Any integrations using it will stop working. Continue?')">
                            Regenerate Token
                        </button>
                    </form>
                    <form method="POST" action="{{ route('settings.api.revoke') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger"
                            onclick="return confirm('Revoke the API token? All integrations will stop working immediately.')">
                            Revoke Token
                        </button>
                    </form>
                </div>
                @else
                <p style="color:var(--color-text-muted);margin-bottom:1.5rem;">No API token has been generated yet. Generate one to start using the REST API.</p>
                <form method="POST" action="{{ route('settings.api.generate') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        Generate API Token
                    </button>
                </form>
                @endif
            </div>
        </div>

        @if($hasApiAccess)
        <div class="settings-card" style="margin-top:1.5rem;">
            <div class="settings-card-header">
                <h2 class="settings-card-title">Quick Reference</h2>
            </div>
            <div class="settings-card-body">
                <p style="color:var(--color-text-muted);margin-bottom:1rem;">Pass your token in the request header:</p>
                <pre style="background:var(--color-surface-2);padding:1rem;border-radius:8px;font-size:.85rem;overflow-x:auto;border:1px solid var(--color-border);">Authorization: Bearer YOUR_TOKEN</pre>
                <p style="color:var(--color-text-muted);margin:1rem 0 .5rem;">Base URL: <code>{{ url('/api/v1') }}</code></p>
                <table class="api-ref-table" style="width:100%;font-size:.85rem;border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:2px solid var(--color-border);">
                            <th style="text-align:left;">Method</th>
                            <th style="text-align:left;">Endpoint</th>
                            <th style="text-align:left;">Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach([
                            ['GET',  '/products',       'List products'],
                            ['GET',  '/products/{id}',  'Get product'],
                            ['POST', '/products',       'Create product'],
                            ['PUT',  '/products/{id}',  'Update product'],
                            ['GET',  '/sales',          'List sales'],
                            ['GET',  '/sales/summary',  'Monthly summary'],
                            ['GET',  '/sales/{id}',     'Get sale'],
                            ['GET',  '/customers',      'List customers'],
                            ['GET',  '/customers/{id}', 'Get customer'],
                            ['POST', '/customers',      'Create customer'],
                            ['PUT',  '/customers/{id}', 'Update customer'],
                        ] as [$method, $endpoint, $desc])
                        @php
                            $color = match($method) {
                                'GET'  => 'var(--color-success)',
                                'POST' => 'var(--color-primary)',
                                'PUT'  => 'var(--color-warning,#f59e0b)',
                                default => 'var(--color-text-muted)',
                            };
                        @endphp
                        <tr style="border-bottom:1px solid var(--color-border);">
                            <td data-label="Method">
                                <span style="background:{{ $color }};color:#fff;border-radius:4px;padding:2px 8px;font-size:.75rem;font-weight:700;">{{ $method }}</span>
                            </td>
                            <td data-label="Endpoint"><code>{{ $endpoint }}</code></td>
                            <td data-label="Description" style="color:var(--color-text-muted);">{{ $desc }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    </div>
</div>
</div>{{-- end .page --}}

@push('scripts')
<script>
function copyToken() {
    const text = document.getElementById('plain-token').textContent.trim();
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
