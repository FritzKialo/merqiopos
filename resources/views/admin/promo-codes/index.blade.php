@extends('admin.layouts.app')
@section('title', 'Promo Codes')
@section('subtitle', 'Discount codes tenants can apply to their own Merqio POS subscription')

@section('content')

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Promo Codes</h1>
        <p class="admin-page-subtitle">Discount codes tenants can apply to their own Merqio POS subscription — not the tenant-facing Discounts/Coupons feature they offer their own customers.</p>
    </div>
</div>

@if(session('success'))
    <div class="admin-alert admin-alert-success">
        <i class="ph-bold ph-check-circle"></i>
        {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="admin-alert admin-alert-error">
        <i class="ph-bold ph-warning-circle"></i>
        {{ session('error') }}
    </div>
@endif

<div class="admin-panel" style="margin-bottom:24px;">
    <div class="admin-panel-header">
        <h3 class="admin-panel-title">New Promo Code</h3>
    </div>
    <div style="padding:20px;">
        <form method="POST" action="{{ route('admin.promo-codes.store') }}">
            @csrf

            <div style="display:flex; gap:16px; flex-wrap:wrap;">
                <div class="form-group" style="max-width:220px; flex:1;">
                    <label class="form-label" for="code">Code *</label>
                    <input type="text" id="code" name="code" class="form-control {{ $errors->has('code') ? 'is-invalid' : '' }}"
                           value="{{ old('code') }}" maxlength="40" placeholder="e.g. LAUNCH20" style="text-transform:uppercase;" required>
                    @error('code') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>
                <div class="form-group" style="max-width:180px; flex:1;">
                    <label class="form-label" for="discount_type">Discount Type *</label>
                    <select id="discount_type" name="discount_type" class="form-control" required>
                        <option value="percentage" {{ old('discount_type') === 'percentage' ? 'selected' : '' }}>Percentage</option>
                        <option value="fixed" {{ old('discount_type') === 'fixed' ? 'selected' : '' }}>Fixed amount (KSh)</option>
                    </select>
                </div>
                <div class="form-group" style="max-width:160px; flex:1;">
                    <label class="form-label" for="discount_value">Discount Value *</label>
                    <input type="number" id="discount_value" name="discount_value" class="form-control {{ $errors->has('discount_value') ? 'is-invalid' : '' }}"
                           value="{{ old('discount_value') }}" step="0.01" min="0.01" placeholder="e.g. 20" required>
                    @error('discount_value') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>
                <div class="form-group" style="max-width:160px; flex:1;">
                    <label class="form-label" for="max_redemptions">Max Redemptions</label>
                    <input type="number" id="max_redemptions" name="max_redemptions" class="form-control"
                           value="{{ old('max_redemptions') }}" min="1" placeholder="Unlimited">
                </div>
                <div class="form-group" style="max-width:200px; flex:1;">
                    <label class="form-label" for="expires_at">Expires</label>
                    <input type="datetime-local" id="expires_at" name="expires_at" class="form-control" value="{{ old('expires_at') }}">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Applies To</label>
                <div style="display:flex; gap:16px; flex-wrap:wrap; font-size:13px;">
                    <label style="display:flex; align-items:center; gap:6px; font-weight:400;">
                        <input type="checkbox" name="applicable_plans[]" value="solo" {{ in_array('solo', old('applicable_plans', [])) ? 'checked' : '' }}> Solo
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; font-weight:400;">
                        <input type="checkbox" name="applicable_plans[]" value="growth" {{ in_array('growth', old('applicable_plans', [])) ? 'checked' : '' }}> Growth
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; font-weight:400;">
                        <input type="checkbox" name="applicable_plans[]" value="enterprise" {{ in_array('enterprise', old('applicable_plans', [])) ? 'checked' : '' }}> Enterprise
                    </label>
                </div>
                <span class="form-hint">Leave all unchecked to apply to every plan.</span>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Internal Note</label>
                <input type="text" id="description" name="description" class="form-control" value="{{ old('description') }}" maxlength="255" placeholder="e.g. Shared with the Twitter launch campaign">
            </div>

            <button type="submit" class="admin-btn admin-btn-primary">
                <i class="ph-bold ph-plus"></i> Create Promo Code
            </button>
        </form>
    </div>
</div>

<div class="admin-panel">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Discount</th>
                    <th>Applies To</th>
                    <th>Redeemed</th>
                    <th>Expires</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($promoCodes as $promo)
                <tr>
                    <td data-label="Code"><code>{{ $promo->code }}</code></td>
                    <td data-label="Discount">
                        {{ $promo->discount_type === 'percentage' ? rtrim(rtrim(number_format($promo->discount_value, 2), '0'), '.') . '%' : 'KSh ' . number_format($promo->discount_value, 2) }}
                    </td>
                    <td data-label="Applies To">
                        {{ $promo->applicable_plans ? collect($promo->applicable_plans)->map(fn($p) => ucfirst($p))->join(', ') : 'All plans' }}
                    </td>
                    <td data-label="Redeemed">
                        {{ $promo->redemptions_count }}{{ $promo->max_redemptions ? ' / ' . $promo->max_redemptions : '' }}
                    </td>
                    <td data-label="Expires">{{ $promo->expires_at?->format('d M Y H:i') ?? '—' }}</td>
                    <td data-label="Status">
                        @if(!$promo->is_active)
                            <span class="admin-badge admin-badge-gray">Inactive</span>
                        @elseif($promo->isExpired())
                            <span class="admin-badge admin-badge-red">Expired</span>
                        @elseif($promo->isExhausted())
                            <span class="admin-badge admin-badge-red">Exhausted</span>
                        @else
                            <span class="admin-badge admin-badge-green">Active</span>
                        @endif
                    </td>
                    <td data-label="Created By">{{ $promo->creator?->name ?? '—' }}</td>
                    <td data-label="Actions">
                        <div style="display:flex; gap:6px;">
                            <form method="POST" action="{{ route('admin.promo-codes.toggle', $promo) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="admin-btn admin-btn-gray admin-btn-sm">
                                    {{ $promo->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.promo-codes.destroy', $promo) }}"
                                onsubmit="return confirm('Delete promo code {{ $promo->code }}? This cannot be undone.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="admin-btn admin-btn-gray admin-btn-sm">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center; padding:24px; color:var(--color-text-secondary);">No promo codes yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div style="margin-top:16px;">{{ $promoCodes->links() }}</div>

@endsection
