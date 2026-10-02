@extends('layouts.app')
@section('title', $expenseClaim->reference)
@push('styles')
<style>
@media (max-width: 900px) {
    .ec-show-layout { grid-template-columns: 1fr !important; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $expenseClaim->reference }}</h1>
            <p class="page-subtitle">{{ $expenseClaim->title }}</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            @if($expenseClaim->status === 'draft' && (auth()->id() === $expenseClaim->user_id || auth()->user()->hasAnyRole('owner','overall_manager','manager')))
                <a href="{{ route('expense-claims.edit', $expenseClaim) }}" class="btn btn--outline">&#9998; Edit</a>
            @endif
            @role('owner','manager')
            @php $canActOnClaim = $expenseClaim->user_id !== auth()->id() || auth()->user()->hasRole('owner'); @endphp
            @if($canActOnClaim && $expenseClaim->status === 'submitted')
                <form method="POST" action="{{ route('expense-claims.approve', $expenseClaim) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn--primary" onclick="return confirm('Approve this claim?')">&#10003; Approve</button>
                </form>
                <button type="button" class="btn btn--outline" style="color:var(--color-danger)" onclick="document.getElementById('rejectModal').style.display='flex'">&#10007; Reject</button>
            @endif
            @if($canActOnClaim && $expenseClaim->status === 'approved')
                <form method="POST" action="{{ route('expense-claims.pay', $expenseClaim) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn--primary" onclick="return confirm('Mark as paid?')">&#128176; Mark Paid</button>
                </form>
            @endif
            @if($expenseClaim->status !== 'paid')
                <form method="POST" action="{{ route('expense-claims.destroy', $expenseClaim) }}" style="display:inline;"
                      onsubmit="return confirm('Delete claim {{ addslashes($expenseClaim->reference) }}?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn--outline" style="color:var(--color-danger)">Delete</button>
                </form>
            @endif
            @endrole
            <a href="{{ route('expense-claims.index') }}" class="btn btn--outline">&larr; Back</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert--success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert--danger">{{ session('error') }}</div>
    @endif

    @if($expenseClaim->status === 'rejected' && $expenseClaim->rejection_reason)
        <div class="alert alert--danger">
            <strong>Rejected:</strong> {{ $expenseClaim->rejection_reason }}
        </div>
    @endif

    <div class="ec-show-layout" style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;">
        <div>
            <div class="card" style="margin-bottom:1.5rem;">
                <div class="card-body">
                    <h3 style="margin:0 0 1rem;">Claim Information</h3>
                    <table class="table-plain" style="width:100%;border-collapse:collapse;">
                        <tr><td style="padding:6px 0;color:var(--color-text-muted);width:160px;">Reference</td><td><strong>{{ $expenseClaim->reference }}</strong></td></tr>
                        <tr><td style="padding:6px 0;color:var(--color-text-muted);">Title</td><td>{{ $expenseClaim->title }}</td></tr>
                        <tr><td style="padding:6px 0;color:var(--color-text-muted);">Submitted By</td><td>{{ $expenseClaim->user ? $expenseClaim->user->name : '—' }}</td></tr>
                        <tr><td style="padding:6px 0;color:var(--color-text-muted);">Approved By</td><td>{{ $expenseClaim->approver ? $expenseClaim->approver->name : '—' }}</td></tr>
                        <tr><td style="padding:6px 0;color:var(--color-text-muted);">Submitted Date</td><td>{{ $expenseClaim->submitted_at ? $expenseClaim->submitted_at->format('d M Y') : '—' }}</td></tr>
                        @if($expenseClaim->paid_at)
                        <tr><td style="padding:6px 0;color:var(--color-text-muted);">Paid Date</td><td>{{ $expenseClaim->paid_at->format('d M Y') }}</td></tr>
                        @endif
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h3 style="margin:0 0 1rem;">Expense Items</h3>
                    <table class="table">
                        <thead>
                            <tr><th>Description</th><th>Date</th><th>Category</th><th>Amount</th></tr>
                        </thead>
                        <tbody>
                            @foreach($expenseClaim->items as $item)
                            <tr>
                                <td data-label="Description">{{ $item->description }}</td>
                                <td data-label="Date">{{ $item->expense_date->format('d M Y') }}</td>
                                <td data-label="Category">{{ $item->category ?: '—' }}</td>
                                <td data-label="Amount">KSh {{ number_format($item->amount, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" style="text-align:right;font-weight:700;">Total:</td>
                                <td><strong>KSh {{ number_format($expenseClaim->total_amount, 2) }}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-body" style="text-align:center;padding:1.5rem;">
                    <div style="font-size:2rem;font-weight:700;color:var(--color-primary);">KSh {{ number_format($expenseClaim->total_amount, 2) }}</div>
                    <div style="color:var(--color-text-muted);margin-top:4px;">Total Claim Amount</div>
                    <div style="margin-top:1.5rem;">
                        @php
                            $cls = match($expenseClaim->status) {
                                'approved','paid' => 'badge-success',
                                'rejected'        => 'badge-danger',
                                'submitted'       => 'badge-warning',
                                default           => 'badge-secondary',
                            };
                        @endphp
                        <span class="badge {{ $cls }}" style="font-size:1.1rem;padding:8px 16px;">{{ ucfirst($expenseClaim->status) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Reject Modal --}}
<div id="rejectModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--color-bg,white);border-radius:8px;padding:1.5rem;width:400px;max-width:90vw;">
        <h3 style="margin:0 0 1rem;">Reject Claim</h3>
        <form method="POST" action="{{ route('expense-claims.reject', $expenseClaim) }}">
            @csrf
            <div class="form-group">
                <label class="form-label">Reason for Rejection</label>
                <textarea name="rejection_reason" class="form-control" rows="3" placeholder="Explain the reason..."></textarea>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:1rem;">
                <button type="button" class="btn btn--outline" onclick="document.getElementById('rejectModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn--primary" style="background:var(--color-danger);">Reject</button>
            </div>
        </form>
    </div>
</div>
@endsection
