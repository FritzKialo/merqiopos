@extends('layouts.app')
@section('title', 'SMS Settings')

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
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        {{-- Status Banner --}}
        @if(!empty($business->sms_provider) && !empty($business->sms_api_key))
            <div class="alert alert-success" style="margin-bottom: var(--space-5);">
                SMS is configured using
                <strong>
                    @if($business->sms_provider === 'africas_talking') Africa's Talking
                    @elseif($business->sms_provider === 'twilio') Twilio
                    @else {{ ucfirst($business->sms_provider) }}
                    @endif
                </strong>.
                SMS notifications are active for your business.
            </div>
        @else
            <div class="alert alert-warning" style="margin-bottom: var(--space-5);">
                SMS is <strong>not configured</strong> yet.
                Add your provider credentials below to enable SMS notifications.
            </div>
        @endif

        {{-- How it works --}}
        <div class="settings-card" style="margin-bottom: var(--space-5);">
            <div class="settings-card-header">
                <h2>How it works</h2>
            </div>
            <div class="settings-card-body">
                <div class="settings-grid-3" style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--space-4);">
                    <div style="text-align: center; padding: var(--space-4);">
                        <div style="width: 32px; height: 32px; margin: 0 auto var(--space-2); border-radius: 50%; background: var(--color-surface-3); color: var(--color-text); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: var(--text-sm);">1</div>
                        <div style="font-weight: 700; color: var(--color-text); margin-bottom: var(--space-1); font-size: var(--text-sm);">Sale recorded</div>
                        <div style="font-size: var(--text-xs); color: var(--color-text-muted);">A sale or payment is created in Merqio POS</div>
                    </div>
                    <div style="text-align: center; padding: var(--space-4);">
                        <div style="width: 32px; height: 32px; margin: 0 auto var(--space-2); border-radius: 50%; background: var(--color-surface-3); color: var(--color-text); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: var(--text-sm);">2</div>
                        <div style="font-weight: 700; color: var(--color-text); margin-bottom: var(--space-1); font-size: var(--text-sm);">SMS sent</div>
                        <div style="font-size: var(--text-xs); color: var(--color-text-muted);">Your provider delivers the message instantly</div>
                    </div>
                    <div style="text-align: center; padding: var(--space-4);">
                        <div style="width: 32px; height: 32px; margin: 0 auto var(--space-2); border-radius: 50%; background: var(--color-surface-3); color: var(--color-text); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: var(--text-sm);">3</div>
                        <div style="font-weight: 700; color: var(--color-text); margin-bottom: var(--space-1); font-size: var(--text-sm);">Customer notified</div>
                        <div style="font-size: var(--text-xs); color: var(--color-text-muted);">Customer receives the SMS on their phone</div>
                    </div>
                </div>
                <div style="
                    background: var(--color-surface-2);
                    border: 1px solid var(--color-border);
                    border-radius: var(--radius-md);
                    padding: var(--space-4);
                    font-size: var(--text-sm);
                    color: var(--color-text-muted);
                    margin-top: var(--space-4);">
                    Supported providers:
                    <a href="https://africastalking.com" target="_blank" style="color: var(--color-primary);">Africa's Talking</a>
                    (Kenya, Nigeria, Uganda, Tanzania) and
                    <a href="https://www.twilio.com" target="_blank" style="color: var(--color-primary);">Twilio</a>
                    (worldwide). Get your API keys from their respective developer portals.
                </div>
            </div>
        </div>

        {{-- Credentials Form --}}
        <div class="settings-card" style="margin-bottom: var(--space-5);">
            <div class="settings-card-header">
                <h2>SMS Provider Credentials</h2>
                <p>These credentials are used to send SMS notifications to your customers.</p>
            </div>
            <div class="settings-card-body">
                <form method="POST" action="{{ route('settings.sms.update') }}">
                    @csrf
                    @method('PUT')

                    {{-- Provider Selection --}}
                    <div class="form-group" style="margin-bottom: var(--space-5);">
                        <label class="form-label">SMS Provider</label>
                        <div class="settings-provider-row" style="display: flex; gap: var(--space-3); flex-wrap: wrap;">
                            <label style="
                                display: flex;
                                align-items: center;
                                gap: var(--space-2);
                                padding: var(--space-3) var(--space-4);
                                border: 1.5px solid var(--color-border);
                                border-radius: var(--radius-md);
                                cursor: pointer;
                                flex: 1;
                                min-width: 150px;
                                transition: var(--transition);"
                                id="provider-none-label">
                                <input type="radio" name="sms_provider" value=""
                                    {{ old('sms_provider', $business->sms_provider ?? '') === '' ? 'checked' : '' }}
                                    onchange="updateProviderLabels(); toggleProviderFields();">
                                <div>
                                    <div style="font-weight: 600; font-size: var(--text-sm); color: var(--color-text);">Disabled</div>
                                    <div style="font-size: var(--text-xs); color: var(--color-text-muted);">No SMS notifications</div>
                                </div>
                            </label>
                            <label style="
                                display: flex;
                                align-items: center;
                                gap: var(--space-2);
                                padding: var(--space-3) var(--space-4);
                                border: 1.5px solid var(--color-border);
                                border-radius: var(--radius-md);
                                cursor: pointer;
                                flex: 1;
                                min-width: 150px;
                                transition: var(--transition);"
                                id="provider-at-label">
                                <input type="radio" name="sms_provider" value="africas_talking"
                                    {{ old('sms_provider', $business->sms_provider ?? '') === 'africas_talking' ? 'checked' : '' }}
                                    onchange="updateProviderLabels(); toggleProviderFields();">
                                <div>
                                    <div style="font-weight: 600; font-size: var(--text-sm); color: var(--color-text);">Africa's Talking</div>
                                    <div style="font-size: var(--text-xs); color: var(--color-text-muted);">Best for Kenya &amp; East Africa</div>
                                </div>
                            </label>
                            <label style="
                                display: flex;
                                align-items: center;
                                gap: var(--space-2);
                                padding: var(--space-3) var(--space-4);
                                border: 1.5px solid var(--color-border);
                                border-radius: var(--radius-md);
                                cursor: pointer;
                                flex: 1;
                                min-width: 150px;
                                transition: var(--transition);"
                                id="provider-twilio-label">
                                <input type="radio" name="sms_provider" value="twilio"
                                    {{ old('sms_provider', $business->sms_provider ?? '') === 'twilio' ? 'checked' : '' }}
                                    onchange="updateProviderLabels(); toggleProviderFields();">
                                <div>
                                    <div style="font-weight: 600; font-size: var(--text-sm); color: var(--color-text);">Twilio</div>
                                    <div style="font-size: var(--text-xs); color: var(--color-text-muted);">Worldwide coverage</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- API Fields (shown when a provider is selected) --}}
                    <div id="smsApiFields">
                        <div class="form-grid-2">
                            {{-- API Key --}}
                            <div class="form-group">
                                <label class="form-label" for="sms_api_key" id="apiKeyLabel">
                                    API Key
                                </label>
                                <input
                                    type="password"
                                    id="sms_api_key"
                                    name="sms_api_key"
                                    class="form-control {{ $errors->has('sms_api_key') ? 'is-invalid' : '' }}"
                                    value="{{ old('sms_api_key') }}"
                                    placeholder="{{ $business->sms_api_key ? '••••••••••••  (saved — leave blank to keep)' : 'Your API key' }}"
                                    autocomplete="new-password">
                                @error('sms_api_key')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                                <span style="font-size: var(--text-xs); color: var(--color-text-muted); margin-top: 4px; display: block;" id="apiKeyHint">
                                    Africa's Talking: API Key from your dashboard. Twilio: Auth Token.
                                </span>
                            </div>

                            {{-- Username --}}
                            <div class="form-group">
                                <label class="form-label" for="sms_username" id="usernameLabel">
                                    Username
                                </label>
                                <input
                                    type="text"
                                    id="sms_username"
                                    name="sms_username"
                                    class="form-control {{ $errors->has('sms_username') ? 'is-invalid' : '' }}"
                                    value="{{ old('sms_username', $business->sms_username) }}"
                                    placeholder="Your username or Account SID">
                                @error('sms_username')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                                <span style="font-size: var(--text-xs); color: var(--color-text-muted); margin-top: 4px; display: block;" id="usernameHint">
                                    Africa's Talking: your AT username. Twilio: Account SID.
                                </span>
                            </div>
                        </div>

                        {{-- Sender ID --}}
                        <div class="form-group">
                            <label class="form-label" for="sms_sender_id">
                                Sender ID
                                <span style="color: var(--color-text-muted); font-weight: 400;">(optional)</span>
                            </label>
                            <input
                                type="text"
                                id="sms_sender_id"
                                name="sms_sender_id"
                                class="form-control {{ $errors->has('sms_sender_id') ? 'is-invalid' : '' }}"
                                value="{{ old('sms_sender_id', $business->sms_sender_id) }}"
                                placeholder="e.g. MYBIZ or +254700000000"
                                maxlength="11">
                            @error('sms_sender_id')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                            <span style="font-size: var(--text-xs); color: var(--color-text-muted); margin-top: 4px; display: block;">
                                The name or number shown to recipients. Max 11 characters for alphanumeric sender IDs.
                            </span>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            Save SMS Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Test SMS --}}
        <div class="settings-card">
            <div class="settings-card-header">
                <h2>Test SMS</h2>
                <p>Send a test message to verify your configuration is working.</p>
            </div>
            <div class="settings-card-body">
                @if(!empty($business->sms_provider) && !empty($business->sms_api_key))
                    <form method="POST" action="{{ route('settings.sms.test') }}">
                        @csrf
                        <div class="form-group" style="max-width: 360px;">
                            <label class="form-label" for="test_phone">Phone Number</label>
                            <input
                                type="text"
                                id="test_phone"
                                name="test_phone"
                                class="form-control {{ $errors->has('test_phone') ? 'is-invalid' : '' }}"
                                value="{{ old('test_phone') }}"
                                placeholder="e.g. 0712345678">
                            @error('test_phone')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                            <span style="font-size: var(--text-xs); color: var(--color-text-muted); margin-top: 4px; display: block;">
                                Enter a phone number to receive the test SMS.
                            </span>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-outline">
                                Send Test SMS
                            </button>
                        </div>
                    </form>
                @else
                    <p style="color: var(--color-text-muted); font-size: var(--text-sm);">
                        Configure and save your SMS credentials first before testing.
                    </p>
                @endif
            </div>
        </div>

    </div>
</div>
</div>{{-- end .page --}}
@endsection

@push('scripts')
<script>
function updateProviderLabels() {
    const none    = document.querySelector('input[name="sms_provider"][value=""]');
    const at      = document.querySelector('input[name="sms_provider"][value="africas_talking"]');
    const twilio  = document.querySelector('input[name="sms_provider"][value="twilio"]');

    const noneLabel   = document.getElementById('provider-none-label');
    const atLabel     = document.getElementById('provider-at-label');
    const twilioLabel = document.getElementById('provider-twilio-label');

    const primary = 'var(--color-primary)';
    const border  = 'var(--color-border)';
    const light   = 'var(--color-primary-light)';

    noneLabel.style.borderColor   = none?.checked   ? primary : border;
    atLabel.style.borderColor     = at?.checked     ? primary : border;
    twilioLabel.style.borderColor = twilio?.checked ? primary : border;

    noneLabel.style.background    = none?.checked   ? light : '';
    atLabel.style.background      = at?.checked     ? light : '';
    twilioLabel.style.background  = twilio?.checked ? light : '';
}

function toggleProviderFields() {
    const none   = document.querySelector('input[name="sms_provider"][value=""]');
    const fields = document.getElementById('smsApiFields');
    if (fields) {
        fields.style.display = none?.checked ? 'none' : 'block';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    updateProviderLabels();
    toggleProviderFields();
});
</script>
@endpush
