@extends('layouts.app')
@section('title', 'New Campaign')
@push('styles')
<style>
@media (max-width: 640px) {
    .campaign-form-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h1>New Campaign</h1>
    <a href="{{ route('campaigns.index') }}" class="btn btn-secondary">← Back</a>
</div>
@if($errors->any())<div class="alert alert-danger"><ul style="margin:0;padding-left:20px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('campaigns.store') }}">
@csrf
<div class="card" style="margin-bottom:16px;">
<div class="card-body">
    <div class="campaign-form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
        <div>
            <label style="display:block;font-weight:600;margin-bottom:4px;">Campaign Name *</label>
            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required placeholder="e.g. January Promotion">
        </div>
        <div>
            <label style="display:block;font-weight:600;margin-bottom:4px;">Channel *</label>
            <select name="channel" class="form-control" id="channel-select">
                <option value="sms" {{ old('channel')=='sms'?'selected':'' }}>SMS</option>
                <option value="email" {{ old('channel')=='email'?'selected':'' }}>Email</option>
                <option value="whatsapp" {{ old('channel')=='whatsapp'?'selected':'' }}>WhatsApp</option>
            </select>
        </div>
    </div>

    <div style="margin-bottom:16px;">
        <label style="display:block;font-weight:600;margin-bottom:4px;">Audience Segment *</label>
        <select name="segment_type" class="form-control" id="segment-select" onchange="toggleSegmentOptions()">
            <option value="all">All Customers</option>
            <option value="by_tag">By Tag</option>
            <option value="by_tier">By Loyalty Tier</option>
            <option value="inactive">Inactive Customers</option>
        </select>
    </div>

    <div id="opt-by_tag" class="segment-opt" style="display:none;margin-bottom:16px;">
        <label style="display:block;font-weight:600;margin-bottom:4px;">Select Tags</label>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            @foreach($tags as $tag)
            <label style="display:flex;align-items:center;gap:4px;cursor:pointer;padding:4px 8px;border:1px solid #ccc;border-radius:4px;">
                <input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}"> {{ $tag->name }}
            </label>
            @endforeach
        </div>
    </div>

    <div id="opt-by_tier" class="segment-opt" style="display:none;margin-bottom:16px;">
        <label style="display:block;font-weight:600;margin-bottom:4px;">Tier Name</label>
        <input type="text" name="tier_name" class="form-control" placeholder="e.g. Gold">
    </div>

    <div id="opt-inactive" class="segment-opt" style="display:none;margin-bottom:16px;">
        <label style="display:block;font-weight:600;margin-bottom:4px;">Inactive for (days)</label>
        <input type="number" name="inactive_days" class="form-control" value="30" min="1">
    </div>

    <div style="margin-bottom:8px;">
        <button type="button" onclick="previewCount()" class="btn btn-secondary" style="font-size:0.85rem;">Preview Recipient Count</button>
        <span id="preview-result" style="margin-left:12px;color:#555;"></span>
    </div>
</div>
</div>

<div class="card">
<div class="card-body">
    <div style="margin-bottom:16px;">
        <label style="display:block;font-weight:600;margin-bottom:4px;">Message *</label>
        <p style="font-size:0.85rem;color:#888;margin:0 0 8px;">Use <code>{name}</code> for customer name, <code>{business_name}</code> for your business name.</p>
        <textarea name="message_template" id="message-template" class="form-control" rows="5" maxlength="1000" required placeholder="Hi {name}, ...">{{ old('message_template') }}</textarea>
        <div style="text-align:right;font-size:0.8rem;color:#888;margin-top:4px;">
            <span id="char-count">0</span> / 160 chars (SMS)
        </div>
    </div>
</div>
</div>

<div style="margin-top:16px;display:flex;gap:12px;">
    <button type="submit" class="btn btn-primary">Create Campaign</button>
    <a href="{{ route('campaigns.index') }}" class="btn btn-secondary">Cancel</a>
</div>
</form>

<script>
function toggleSegmentOptions() {
    document.querySelectorAll('.segment-opt').forEach(el => el.style.display = 'none');
    const val = document.getElementById('segment-select').value;
    const opt = document.getElementById('opt-' + val);
    if (opt) opt.style.display = 'block';
}

function previewCount() {
    const segType = document.getElementById('segment-select').value;
    const params = new URLSearchParams({ segment_type: segType });
    const tierName = document.querySelector('[name="tier_name"]')?.value;
    const inactiveDays = document.querySelector('[name="inactive_days"]')?.value;
    const channel = document.querySelector('[name="channel"]')?.value;
    if (channel) params.append('channel', channel);
    if (tierName) params.append('tier_name', tierName);
    if (inactiveDays) params.append('inactive_days', inactiveDays);
    document.querySelectorAll('[name="tag_ids[]"]:checked').forEach(cb => params.append('tag_ids[]', cb.value));
    fetch('{{ route("campaigns.preview") }}?' + params.toString())
        .then(r => r.json())
        .then(data => {
            document.getElementById('preview-result').textContent = data.count + ' recipients';
        });
}

const msgArea = document.getElementById('message-template');
const charCount = document.getElementById('char-count');
function updateCount() { charCount.textContent = msgArea.value.length; }
msgArea.addEventListener('input', updateCount);
updateCount();
</script>
@endsection
