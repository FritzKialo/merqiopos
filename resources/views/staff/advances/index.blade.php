@extends('layouts.app')
@section('title', 'Salary Advances')

@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ ($mine ?? false) ? 'My Advances' : 'Salary Advances' }}</h1>
            <p class="page-subtitle">{{ ($mine ?? false) ? 'Your salary advance requests and their status.' : 'Request and manage salary advances.' }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- Request form --}}
    <div class="table-card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
        <h3 style="margin:0 0 1rem; font-size:0.95rem;">Request a Salary Advance</h3>
        <form method="POST" action="{{ route('staff.advances.store') }}" style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end;">
            @csrf
            <div>
                <label class="form-label">Amount (KSh)</label>
                <input type="number" name="amount" class="form-control" placeholder="5000" min="1" step="1"
                       value="{{ old('amount') }}" style="width:160px;">
            </div>
            <div style="flex:1; min-width:200px;">
                <label class="form-label">Reason (optional)</label>
                <input type="text" name="reason" class="form-control" placeholder="e.g. school fees" value="{{ old('reason') }}">
            </div>
            <button type="submit" class="btn btn--primary">Submit Request</button>
        </form>
        @if($errors->any())
            <div style="margin-top:0.5rem; color:var(--color-danger); font-size:0.85rem;">
                {{ $errors->first() }}
            </div>
        @endif
    </div>

    {{-- List --}}
    <div class="table-section">
        @if($advances->isEmpty())
            <div class="empty-state">
                <h3>No salary advance requests</h3>
            </div>
        @else
            <div class="table-wrapper">
                <table class="table-responsive-cards">
                    <thead>
                        <tr>
                            @if($canManage)<th>Employee</th>@endif
                            <th>Amount</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Requested</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($advances as $adv)
                        <tr>
                            @if($canManage)
                            <td data-label="Employee">{{ $adv->user->name }}</td>
                            @endif
                            <td data-label="Amount"><strong>KSh {{ number_format($adv->amount, 0) }}</strong></td>
                            <td data-label="Reason" style="color:var(--color-text-muted); font-size:0.85rem;">{{ $adv->reason ?? '—' }}</td>
                            <td data-label="Status">
                                @if($adv->status === 'approved')
                                    <span class="badge badge-success">Approved</span>
                                @elseif($adv->status === 'rejected')
                                    <span class="badge badge-danger">Rejected</span>
                                @elseif($adv->status === 'deducted')
                                    <span class="badge badge-secondary">Deducted</span>
                                @else
                                    <span class="badge badge-warning">Pending</span>
                                @endif
                            </td>
                            <td data-label="Requested" style="color:var(--color-text-muted); font-size:0.8rem;">
                                {{ $adv->created_at->format('d M Y') }}
                                @if($adv->approved_at)
                                    <br><span>{{ $adv->status === 'approved' ? 'Approved' : 'Decided' }}: {{ $adv->approved_at->format('d M Y') }}</span>
                                @endif
                            </td>
                            <td data-label="Actions">
                                <div class="action-buttons">
                                    @if($adv->isPending())
                                        @if($canManage && $adv->user_id !== auth()->id())
                                        <form method="POST" action="{{ route('staff.advances.approve', $adv) }}" style="display:inline;">
                                            @csrf @method('PATCH')
                                            <button class="btn btn--success btn--sm">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('staff.advances.reject', $adv) }}" style="display:inline;"
                                              onsubmit="return confirm('Reject this advance request?')">
                                            @csrf @method('PATCH')
                                            <button class="btn btn--danger btn--sm">Reject</button>
                                        </form>
                                        @endif
                                        @if($adv->user_id === auth()->id())
                                        <form method="POST" action="{{ route('staff.advances.destroy', $adv) }}" style="display:inline;"
                                              onsubmit="return confirm('Delete this request?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn--outline btn--sm">Delete</button>
                                        </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $advances->links('vendor.pagination.custom') }}
        @endif
    </div>
</div>
@endsection
