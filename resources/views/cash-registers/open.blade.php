@extends('layouts.app')
@section('title', 'Open Cash Register')
@section('content')
<div class="page-header"><h1>Open Cash Register</h1></div>
<div class="card" style="max-width:500px;">
<div class="card-body">
<p style="color:#555;margin-bottom:20px;">Count your opening float and enter the amount below to start a new register session.</p>
<form method="POST" action="{{ route('cash-registers.open') }}">
@csrf
<div style="margin-bottom:16px;">
    <label class="form-label">Opening Float (KSh) *</label>
    <input type="number" name="opening_float" class="form-control" step="0.01" min="0" value="0" required>
    <p style="color:#888;font-size:0.85rem;margin-top:4px;">The cash amount in the till at the start of the session.</p>
</div>
<div style="margin-bottom:20px;">
    <label class="form-label">Notes (optional)</label>
    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Morning shift, counted by John..."></textarea>
</div>
<div style="display:flex;gap:12px;">
    <button type="submit" class="btn btn-primary">Open Register</button>
    <a href="{{ route('cash-registers.index') }}" class="btn btn-secondary">Cancel</a>
</div>
</form>
</div>
</div>
@endsection
