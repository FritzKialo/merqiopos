@extends('layouts.app')
@section('title', 'Email Domain')

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

        <div class="settings-card">
            <div class="settings-card-header">
                <h2 class="settings-card-title">Campaign Email Sender</h2>
                <p>Choose what customers see as the "From" name and address on marketing campaigns.</p>
            </div>
            <div class="settings-card-body">

                @if($business->hasVerifiedSendingDomain())
                    {{-- Verified: genuinely sending as their own domain --}}
                    <div class="alert alert-success" style="margin-bottom:var(--space-5);">
                        <div>
                            <div style="font-weight:600; color:var(--color-text);">Verified — campaigns send as your domain</div>
                            <div style="font-size:var(--text-sm); color:var(--color-text-muted);">
                                Customers now see <strong>{{ $business->name }} &lt;no-reply@{{ $business->sending_domain }}&gt;</strong>,
                                with replies going to <strong>{{ $business->email }}</strong>.
                            </div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('settings.email-domain.destroy') }}"
                        onsubmit="return confirm('Remove {{ addslashes($business->sending_domain) }}? Campaigns will go back to sending from the shared Merqio address.');">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger">Remove Custom Domain</button>
                    </form>

                @elseif($business->sending_domain)
                    {{-- Domain saved, DNS records not verified yet --}}
                    <div class="alert alert-warning" style="margin-bottom:var(--space-5);">
                        <div>
                            <div style="font-weight:600; color:var(--color-text);">Almost there — add these DNS records</div>
                            <div style="font-size:var(--text-sm); color:var(--color-text-muted);">
                                Add both records at your domain registrar for <strong>{{ $business->sending_domain }}</strong>, then click Verify below.
                                DNS changes can take a few minutes to a few hours to take effect.
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom:var(--space-5);">
                        <p style="font-weight:600; margin-bottom:6px;">1. DKIM record (proves the email is really from you)</p>
                        <table class="table-plain" style="width:100%; font-size:var(--text-sm);">
                            <tbody>
                                <tr>
                                    <td style="color:var(--color-text-muted); width:80px;">Type</td>
                                    <td><code>TXT</code></td>
                                </tr>
                                <tr>
                                    <td style="color:var(--color-text-muted);">Host</td>
                                    <td><code style="word-break:break-all;">{{ $dnsRecords['dkim']['host'] }}</code></td>
                                </tr>
                                <tr>
                                    <td style="color:var(--color-text-muted);">Value</td>
                                    <td><code style="word-break:break-all; display:block; background:var(--color-surface-2); padding:8px; border-radius:6px; margin-top:4px;">{{ $dnsRecords['dkim']['value'] }}</code></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-bottom:var(--space-5);">
                        <p style="font-weight:600; margin-bottom:6px;">2. SPF record (authorizes us to send on your behalf)</p>
                        <table class="table-plain" style="width:100%; font-size:var(--text-sm);">
                            <tbody>
                                <tr>
                                    <td style="color:var(--color-text-muted); width:80px;">Type</td>
                                    <td><code>TXT</code></td>
                                </tr>
                                <tr>
                                    <td style="color:var(--color-text-muted);">Host</td>
                                    <td><code>{{ $dnsRecords['spf']['host'] }}</code> <span style="color:var(--color-text-muted);">(your root domain — often shown as <code>@</code>)</span></td>
                                </tr>
                                <tr>
                                    <td style="color:var(--color-text-muted);">Value</td>
                                    <td><code style="word-break:break-all; display:block; background:var(--color-surface-2); padding:8px; border-radius:6px; margin-top:4px;">{{ $dnsRecords['spf']['value'] }}</code></td>
                                </tr>
                            </tbody>
                        </table>
                        <p style="font-size:var(--text-xs); color:var(--color-text-muted); margin-top:6px;">
                            Already have an SPF record? Add <code>include:merqiopos.com</code> into your existing one instead of creating a second — a domain can only have one SPF record.
                        </p>
                    </div>

                    <div class="form-actions" style="display:flex; gap:var(--space-3); flex-wrap:wrap;">
                        <form method="POST" action="{{ route('settings.email-domain.verify') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">Verify Now</button>
                        </form>
                        <form method="POST" action="{{ route('settings.email-domain.destroy') }}"
                            onsubmit="return confirm('Cancel setup for {{ addslashes($business->sending_domain) }}?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline">Cancel / Start Over</button>
                        </form>
                    </div>

                @else
                    {{-- No custom domain set — explain the default, offer to add one --}}
                    <div class="alert alert-info" style="margin-bottom:var(--space-5);">
                        <div>
                            <div style="font-weight:600; color:var(--color-text);">Currently sending as: {{ $business->name }} &lt;no-reply@merqiopos.com&gt;</div>
                            <div style="font-size:var(--text-sm); color:var(--color-text-muted);">
                                Replies go to {{ $business->email }}. This works out of the box for every business and won't get filtered as spam.
                                If you own a domain (e.g. yourbusiness.co.ke), you can verify it below so campaigns send as your own address instead.
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('settings.email-domain.store') }}" style="max-width:420px;">
                        @csrf
                        <div class="form-group">
                            <label class="form-label" for="sending_domain">Your domain</label>
                            <input type="text" id="sending_domain" name="sending_domain"
                                class="form-control {{ $errors->has('sending_domain') ? 'is-invalid' : '' }}"
                                value="{{ old('sending_domain') }}" placeholder="yourbusiness.co.ke" required>
                            @error('sending_domain') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            <span class="form-hint">Just the domain — no https:// and no @.</span>
                        </div>
                        <button type="submit" class="btn btn-primary">Generate DNS Records</button>
                    </form>
                @endif

            </div>
        </div>

    </div>
</div>
</div>{{-- end .page --}}
@endsection
