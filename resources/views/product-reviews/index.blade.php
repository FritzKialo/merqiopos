@extends('layouts.app')
@section('title', 'Product Reviews')
@push('styles')
<style>
@media (min-width: 769px) {
    .pr-table th, .pr-table td { padding: 12px 16px; }
}
</style>
@endpush
@section('content')
<div class="page-header">
    <h1>Product Reviews</h1>
</div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="card">
<div class="card-body" style="padding:0;">
<table class="pr-table" style="width:100%;border-collapse:collapse;">
<thead><tr style="border-bottom:1px solid var(--color-border);background:var(--color-surface-2);">
    <th style="text-align:left;">Product</th>
    <th style="text-align:left;">Reviewer</th>
    <th style="text-align:center;width:100px;">Rating</th>
    <th style="text-align:left;">Review</th>
    <th style="text-align:center;width:90px;">Date</th>
    <th style="text-align:center;width:80px;">Status</th>
    <th style="text-align:center;width:160px;">Actions</th>
</tr></thead>
<tbody>
@forelse($reviews as $review)
<tr style="border-bottom:1px solid var(--color-border);">
    <td data-label="Product" style="font-weight:500;">{{ $review->product->name ?? '—' }}</td>
    <td data-label="Reviewer">
        <div>{{ $review->reviewer_name }}</div>
        @if($review->reviewer_email)<div class="text-muted" style="font-size:0.8rem;">{{ $review->reviewer_email }}</div>@endif
    </td>
    <td data-label="Rating" style="text-align:center;">
        <span style="color:#f59e0b;">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
    </td>
    <td data-label="Review" style="max-width:300px;">
        <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:300px;" title="{{ $review->review_body }}">{{ $review->review_body ?? '—' }}</div>
    </td>
    <td data-label="Date" class="text-muted" style="text-align:center;font-size:0.8rem;">{{ $review->created_at->format('d M Y') }}</td>
    <td data-label="Status" style="text-align:center;">
        @if($review->status === 'pending')<span class="badge badge-warning">Pending</span>
        @elseif($review->status === 'approved')<span class="badge badge-success">Approved</span>
        @else<span class="badge badge-danger">Rejected</span>@endif
    </td>
    <td data-label="Actions" style="text-align:center;">
        @if($review->status !== 'approved')
        <form method="POST" action="{{ route('product-reviews.approve', $review) }}" style="display:inline;">
            @csrf <button type="submit" class="btn btn-sm btn-success">Approve</button>
        </form>
        @endif
        @if($review->status !== 'rejected')
        <form method="POST" action="{{ route('product-reviews.reject', $review) }}" style="display:inline;">
            @csrf <button type="submit" class="btn btn-sm btn-secondary">Reject</button>
        </form>
        @endif
        <form method="POST" action="{{ route('product-reviews.destroy', $review) }}" style="display:inline;" onsubmit="return confirm('Delete this review?')">
            @csrf @method('DELETE') <button type="submit" class="btn btn-sm btn-danger">Delete</button>
        </form>
    </td>
</tr>
@empty
<tr><td colspan="7" class="text-muted" style="padding:32px;text-align:center;">No reviews yet.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
<div style="margin-top:16px;">{{ $reviews->links() }}</div>
@endsection
