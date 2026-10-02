@extends('layouts.app')
@section('title', 'Leave Requests')

@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ ($mine ?? false) ? 'My Leave' : 'Leave Requests' }}</h1>
            <p class="page-subtitle">{{ ($mine ?? false) ? 'Your leave applications and their status.' : 'Manage staff leave applications.' }}</p>
        </div>
        <div class="action-buttons">
            <a href="{{ route('staff.leave.create') }}" class="btn btn--primary">Apply for Leave</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Filters --}}
    <form method="GET" action="{{ route('staff.leave.index') }}">
        <div class="toolbar">
            @if($employees)
            <select name="user_id" class="toolbar-select" onchange="this.form.submit()">
                <option value="">All Employees</option>
                @foreach($employees as $emp)
                    <option value="{{ $emp->id }}" {{ request('user_id') == $emp->id ? 'selected' : '' }}>{{ $emp->name }}</option>
                @endforeach
            </select>
            @endif
            <select name="status" class="toolbar-select" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
            @if(request()->hasAny(['status','user_id']))
                <a href="{{ route('staff.leave.index') }}" class="btn btn--outline btn--sm">Clear</a>
            @endif
        </div>
    </form>

    <div class="table-section">
        @if($requests->isEmpty())
            <div class="empty-state">
                <h3>No leave requests</h3>
                <p>No requests found for the current filter.</p>
            </div>
        @else
            <div class="table-wrapper">
                <table class="table-responsive-cards">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Leave Type</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Days</th>
                            <th>Status</th>
                            <th>Reason</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($requests as $req)
                        <tr>
                            <td data-label="Employee">{{ $req->user->name }}</td>
                            <td data-label="Leave Type">{{ $req->leaveType->name }}</td>
                            <td data-label="From">{{ $req->start_date->format('d M Y') }}</td>
                            <td data-label="To">{{ $req->end_date->format('d M Y') }}</td>
                            <td data-label="Days">{{ $req->days_requested }}</td>
                            <td data-label="Status">
                                @if($req->status === 'approved')
                                    <span class="badge badge-success">Approved</span>
                                @elseif($req->status === 'rejected')
                                    <span class="badge badge-danger">Rejected</span>
                                @elseif($req->status === 'cancelled')
                                    <span class="badge badge-muted" style="background:#e5e7eb;color:#374151;">Cancelled</span>
                                @else
                                    <span class="badge badge-warning">Pending</span>
                                @endif
                            </td>
                            <td data-label="Reason" style="max-width:180px; font-size:0.8rem; color:var(--color-text-muted);">
                                {{ Str::limit($req->reason, 60) }}
                                @if($req->rejection_reason)
                                    <br><em style="color:var(--color-danger);">{{ Str::limit($req->rejection_reason, 60) }}</em>
                                @endif
                            </td>
                            <td data-label="Actions">
                                <div class="action-buttons">
                                    @if($req->status === 'pending')
                                        @unless($mine ?? false)
                                        @role('owner','overall_manager','manager')
                                        <form method="POST" action="{{ route('staff.leave.approve', $req) }}" style="display:inline;">
                                            @csrf @method('PATCH')
                                            <button class="btn btn--success btn--sm">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('staff.leave.reject', $req) }}" style="display:inline;"
                                              onsubmit="let r=prompt('Rejection reason (optional):'); if(r!==null){this.querySelector('[name=rejection_reason]').value=r; return true;} return false;">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="rejection_reason" value="">
                                            <button class="btn btn--danger btn--sm">Reject</button>
                                        </form>
                                        <form method="POST" action="{{ route('staff.leave.destroy', $req) }}" style="display:inline;"
                                              onsubmit="return confirm('Delete this request?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn--outline btn--sm">Delete</button>
                                        </form>
                                        @endrole
                                        @endunless
                                        @if($req->user_id === auth()->id())
                                        <form method="POST" action="{{ route('staff.leave.destroy', $req) }}" style="display:inline;"
                                              onsubmit="return confirm('Delete this request?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn--outline btn--sm">Delete</button>
                                        </form>
                                        @endif
                                    @endif
                                    @if($req->status === 'approved' && $req->start_date->isFuture() && ($req->user_id === auth()->id() || auth()->user()->hasAnyRole('owner', 'manager')))
                                    <form method="POST" action="{{ route('staff.leave.cancel', $req) }}" style="display:inline;"
                                          onsubmit="return confirm('Cancel this approved leave? The days go back to the balance.')">
                                        @csrf @method('PATCH')
                                        <button class="btn btn--outline btn--sm">Cancel leave</button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $requests->links('vendor.pagination.custom') }}
        @endif
    </div>
</div>
@endsection
