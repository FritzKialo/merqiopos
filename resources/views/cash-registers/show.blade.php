@extends('layouts.app')
@section('title', 'Cash Register Session')
@push('styles')
<style>
@media (min-width: 769px) {
    .cr-entries-table th, .cr-entries-table td { padding: 10px 16px; }
}
@media (max-width: 700px) {
    .cr-summary-grid { grid-template-columns: 1fr !important; }
}
/* The "Add Entry"/"Close Register" column hardcoded a fixed 380px width
   inline (only when the register is open) — a fixed 380px alone exceeds
   most phone viewports outright, forcing horizontal overflow regardless of
   any class-based breakpoint, since inline styles bypass those entirely. */
@media (max-width: 900px) {
    .cr-main-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h1>Cash Register Session</h1>
    <a href="{{ route('cash-registers.index') }}" class="btn btn-secondary">← All Sessions</a>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

{{-- Summary card --}}
<div class="cr-summary-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">
    <div class="card">
        <div class="card-body" style="text-align:center;">
            <div class="text-muted" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Opening Float</div>
            <div style="font-size:1.5rem;font-weight:700;">KSh {{ number_format($register->opening_float, 2) }}</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body" style="text-align:center;">
            <div class="text-muted" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Expected Closing</div>
            <div style="font-size:1.5rem;font-weight:700;">KSh {{ number_format($expected, 2) }}</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body" style="text-align:center;">
            <div class="text-muted" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Status</div>
            <div class="{{ $register->status === 'open' ? 'text-success' : 'text-muted' }}" style="font-size:1.2rem;font-weight:700;">{{ ucfirst($register->status) }}</div>
            <div class="text-muted" style="font-size:0.8rem;">{{ $register->opened_at->format('d M Y H:i') }}</div>
        </div>
    </div>
</div>

<div class="cr-main-grid" style="display:grid;grid-template-columns:1fr {{ $register->isOpen() ? '380px' : '' }};gap:24px;">
    {{-- Entries list --}}
    <div>
        <div class="card">
            <div class="card-body" style="padding:0;">
                <div style="padding:16px;border-bottom:1px solid var(--color-border);font-weight:600;">Register Entries</div>
                <table class="cr-entries-table" style="width:100%;border-collapse:collapse;">
                <thead><tr style="border-bottom:1px solid var(--color-border);background:var(--color-surface-2);">
                    <th style="text-align:left;">Type</th>
                    <th style="text-align:left;">Description</th>
                    <th style="text-align:left;">Reference</th>
                    <th style="text-align:right;">Amount</th>
                    <th style="text-align:left;">Time</th>
                </tr></thead>
                <tbody>
                @forelse($register->entries as $entry)
                @php
                    $isIn = in_array($entry->entry_type, ['sale','float_add']);
                    $typeLabels = ['sale'=>'Sale','refund'=>'Refund','expense'=>'Expense','float_add'=>'Float Add','float_remove'=>'Float Remove'];
                    $typeClasses = ['sale'=>'text-success','refund'=>'text-danger','expense'=>'text-danger','float_add'=>'text-info','float_remove'=>'text-warn'];
                @endphp
                <tr style="border-bottom:1px solid var(--color-border);">
                    <td data-label="Type">
                        <span class="{{ $typeClasses[$entry->entry_type] ?? 'text-muted' }}" style="font-size:0.82rem;font-weight:600;">{{ $typeLabels[$entry->entry_type] ?? $entry->entry_type }}</span>
                    </td>
                    <td data-label="Description">{{ $entry->description }}</td>
                    <td data-label="Reference" class="text-muted" style="font-family:monospace;font-size:0.85rem;">{{ $entry->reference ?? '—' }}</td>
                    <td data-label="Amount" class="{{ $isIn ? 'text-success' : 'text-danger' }}" style="text-align:right;font-weight:600;">
                        {{ $isIn ? '+' : '-' }}{{ number_format($entry->amount, 2) }}
                    </td>
                    <td data-label="Time" class="text-muted" style="font-size:0.82rem;">{{ $entry->created_at->format('H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-muted" style="padding:24px;text-align:center;">No entries yet.</td></tr>
                @endforelse
                </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($register->isOpen())
    {{-- Right column: Add Entry + Close --}}
    <div>
        <div class="card" style="margin-bottom:16px;">
            <div class="card-body">
                <h3 style="margin:0 0 16px;font-size:1rem;">Add Entry</h3>
                <form method="POST" action="{{ route('cash-registers.entry', $register) }}">
                @csrf
                <div style="margin-bottom:12px;">
                    <label class="form-label">Type</label>
                    <select name="entry_type" class="form-control" required>
                        <option value="sale">Sale</option>
                        <option value="refund">Refund</option>
                        <option value="expense">Expense</option>
                        <option value="float_add">Float Add</option>
                        <option value="float_remove">Float Remove</option>
                    </select>
                </div>
                <div style="margin-bottom:12px;">
                    <label class="form-label">Amount (KSh)</label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
                </div>
                <div style="margin-bottom:12px;">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" required placeholder="e.g. Cash sale - John">
                </div>
                <div style="margin-bottom:16px;">
                    <label class="form-label">Reference (optional)</label>
                    <input type="text" name="reference" class="form-control" placeholder="Receipt #, etc.">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">Add Entry</button>
                </form>
            </div>
        </div>

        <div class="card" style="border:2px solid var(--color-danger);">
            <div class="card-body">
                <h3 class="text-danger" style="margin:0 0 8px;font-size:1rem;">Close Register</h3>
                <p class="text-muted" style="font-size:0.85rem;margin-bottom:16px;">Count the cash in the till and enter the actual amount to close the session.</p>
                <form method="POST" action="{{ route('cash-registers.close', $register) }}">
                @csrf
                <div style="margin-bottom:12px;">
                    <label class="form-label">Expected: <strong>KSh {{ number_format($expected, 2) }}</strong></label>
                    <label class="form-label" style="margin-top:8px;">Actual Cash Count (KSh)</label>
                    <input type="number" name="actual_closing" class="form-control" step="0.01" min="0" value="{{ number_format($expected, 2, '.', '') }}" required>
                </div>
                <button type="submit" class="btn btn-danger" style="width:100%;" onclick="return confirm('Close this register session?')">Close Register</button>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
