@extends('layouts.app')
@section('title', 'Stock Transfers')
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Stock Transfers</h1>
            <p class="page-subtitle">Move inventory between stores in your organisation</p>
        </div>
        <a href="{{ route('org.transfers.create') }}" class="btn btn--primary">New Transfer</a>
    </div>

    @if(session('success')) <div class="alert alert--success">{{ session('success') }}</div> @endif
    @if(session('error'))   <div class="alert alert--error">{{ session('error') }}</div> @endif

    <div class="table-card">
        <table class="table">
            <thead>
                <tr>
                    <th>Transfer #</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th>By</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfers as $transfer)
                <tr>
                    <td data-label="Transfer #"><strong>{{ $transfer->transfer_number }}</strong></td>
                    <td data-label="From">{{ $transfer->fromBusiness?->name }}</td>
                    <td data-label="To">{{ $transfer->toBusiness?->name }}</td>
                    <td data-label="Status">
                        @php $sc = ['pending'=>'badge--gray','approved'=>'badge--gray','dispatched'=>'badge--gray','received'=>'badge--green','cancelled'=>'badge--red']; @endphp
                        <span class="badge {{ $sc[$transfer->status] ?? 'badge--gray' }}">{{ ucfirst($transfer->status) }}</span>
                    </td>
                    <td data-label="Requested">{{ $transfer->requested_at?->format('d M Y') }}</td>
                    <td data-label="By">{{ $transfer->requester?->name }}</td>
                    <td data-label=""><a href="{{ route('org.transfers.show', $transfer) }}" class="btn btn--outline btn--sm">View</a></td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:2rem;">No stock transfers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $transfers->links() }}
</div>
@endsection
