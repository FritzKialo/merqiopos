@extends('layouts.app')
@section('title', 'New Stock Count')
@section('content')
<div class="page-header"><h1>Start Physical Stock Count</h1></div>
<div class="card" style="max-width:600px;">
<div class="card-body">
<p style="color:#555;margin-bottom:20px;">This will snapshot the current system stock quantities for all active products. You can then enter the actual counted quantities.</p>
<form method="POST" action="{{ route('inventory.stock-counts.store') }}">
@csrf
<div style="margin-bottom:16px;">
<label class="form-label">Notes (optional)</label>
<textarea name="notes" class="form-control" rows="3" placeholder="e.g. End of month stock take, quarterly audit..."></textarea>
</div>
<div style="background:#fff3cd;border:1px solid #ffc107;border-radius:4px;padding:12px;margin-bottom:16px;font-size:0.9rem;">
Starting a count will lock current quantities as the baseline. This cannot be undone.
</div>
<div style="display:flex;gap:12px;">
<button type="submit" class="btn btn-primary">Start Count</button>
<a href="{{ route('inventory.stock-counts.index') }}" class="btn btn-secondary">Cancel</a>
</div>
</form>
</div>
</div>
@endsection
