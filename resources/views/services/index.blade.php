@extends('layouts.app')
@section('title', 'Services')
@push('styles')
<style>
@media (min-width: 769px) {
    .services-table th, .services-table td { padding: 0.75rem 1rem; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <h1 class="page-title">Services</h1>
        <div style="display:flex;gap:0.5rem;">
            <a href="{{ route('services.create') }}" class="btn btn-primary">+ Add Service</a>
            <a href="{{ route('appointments.index') }}" class="btn btn-secondary">Appointments</a>
        </div>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

    <div class="table-card">
        <table class="services-table" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:1px solid var(--color-border);">
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Name</th>
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Category</th>
                    <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Duration</th>
                    <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Price</th>
                    <th style="text-align:center;font-size:0.8rem;text-transform:uppercase;">Status</th>
                    <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($services as $svc)
                <tr style="border-bottom:1px solid var(--color-border);">
                    <td data-label="Name">
                        <div style="font-weight:600;">{{ $svc->name }}</div>
                        @if($svc->description)
                        <div style="font-size:0.8rem;color:var(--color-text-muted);">{{ Str::limit($svc->description, 60) }}</div>
                        @endif
                    </td>
                    <td data-label="Category" style="color:var(--color-text-muted);">{{ $svc->category ?? '—' }}</td>
                    <td data-label="Duration" style="text-align:right;">{{ $svc->duration_minutes }} min</td>
                    <td data-label="Price" style="text-align:right;font-weight:600;">KES {{ number_format($svc->price, 2) }}</td>
                    <td data-label="Status" style="text-align:center;">
                        @if($svc->is_active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-secondary">Inactive</span>
                        @endif
                    </td>
                    <td data-label="Actions" style="text-align:right;">
                        <a href="{{ route('services.edit', $svc) }}" class="btn btn-secondary" style="padding:0.25rem 0.6rem;font-size:0.8rem;">Edit</a>
                        <form method="POST" action="{{ route('services.destroy', $svc) }}" style="display:inline;" onsubmit="return confirm('Delete this service?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger" style="padding:0.25rem 0.6rem;font-size:0.8rem;">Del</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" style="padding:2rem;text-align:center;color:var(--color-text-muted);">No services yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
