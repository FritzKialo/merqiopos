@php $d = $discount ?? null; @endphp

<div class="form-group">
    <label class="form-label">Name *</label>
    <input type="text" name="name" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
        value="{{ old('name', $d?->name) }}" required>
    @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
</div>

<div class="form-grid-2">
    <div class="form-group">
        <label class="form-label">Code (optional)</label>
        <input type="text" name="code" class="form-control {{ $errors->has('code') ? 'is-invalid' : '' }}"
            value="{{ old('code', $d?->code) }}" placeholder="e.g. SAVE20">
        <span class="form-hint">Customers enter this at POS to apply discount.</span>
        @error('code')<span class="invalid-feedback">{{ $message }}</span>@enderror
    </div>
    <div class="form-group">
        <label class="form-label">Minimum Order Amount (KSh)</label>
        <input type="number" name="min_order_amount" class="form-control"
            value="{{ old('min_order_amount', $d?->min_order_amount) }}" min="0" step="0.01">
    </div>
</div>

<div class="form-grid-2">
    <div class="form-group">
        <label class="form-label">Type *</label>
        <div style="display:flex; gap:16px; margin-top:8px;">
            <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                <input type="radio" name="type" value="percentage"
                    {{ old('type', $d?->type ?? 'percentage') === 'percentage' ? 'checked' : '' }}>
                Percentage (%)
            </label>
            <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                <input type="radio" name="type" value="fixed"
                    {{ old('type', $d?->type) === 'fixed' ? 'checked' : '' }}>
                Fixed Amount (KSh)
            </label>
        </div>
    </div>
    <div class="form-group">
        <label class="form-label">Value *</label>
        <input type="number" name="value" class="form-control {{ $errors->has('value') ? 'is-invalid' : '' }}"
            value="{{ old('value', $d?->value) }}" min="0" step="0.01" required>
        @error('value')<span class="invalid-feedback">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid-2">
    <div class="form-group">
        <label class="form-label">Valid From</label>
        <input type="date" name="valid_from" class="form-control"
            value="{{ old('valid_from', $d?->valid_from?->format('Y-m-d')) }}">
    </div>
    <div class="form-group">
        <label class="form-label">Valid Until</label>
        <input type="date" name="valid_until" class="form-control"
            value="{{ old('valid_until', $d?->valid_until?->format('Y-m-d')) }}">
    </div>
</div>

<div class="form-grid-2">
    <div class="form-group">
        <label class="form-label">Max Uses</label>
        <input type="number" name="max_uses" class="form-control"
            value="{{ old('max_uses', $d?->max_uses) }}" min="1" placeholder="Unlimited">
    </div>
    <div class="form-group">
        <label class="form-label" style="display:flex; align-items:center; gap:8px; cursor:pointer; margin-top:24px;">
            <input type="checkbox" name="is_active" value="1"
                {{ old('is_active', $d ? $d->is_active : true) ? 'checked' : '' }}>
            Active
        </label>
    </div>
</div>
