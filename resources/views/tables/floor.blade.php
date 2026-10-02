@extends('layouts.app')
@section('title', 'Floor Plan')
@section('content')
<div class="page">
    <div class="page-header">
        <h1 class="page-title">Floor Plan</h1>
        <div style="display:flex;gap:0.5rem;">
            <a href="{{ route('tables.manage') }}" class="btn btn-secondary">Manage Tables</a>
        </div>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

    {{-- Legend --}}
    <div style="display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap;font-size:0.82rem;">
        <span style="display:flex;align-items:center;gap:0.4rem;"><span style="width:14px;height:14px;border-radius:3px;background:#d1fae5;display:inline-block;border:1px solid #6ee7b7;"></span> Available</span>
        <span style="display:flex;align-items:center;gap:0.4rem;"><span style="width:14px;height:14px;border-radius:3px;background:#fee2e2;display:inline-block;border:1px solid #fca5a5;"></span> Occupied</span>
        <span style="display:flex;align-items:center;gap:0.4rem;"><span style="width:14px;height:14px;border-radius:3px;background:#fef3c7;display:inline-block;border:1px solid #fcd34d;"></span> Reserved</span>
        <span style="display:flex;align-items:center;gap:0.4rem;"><span style="width:14px;height:14px;border-radius:3px;background:#f3f4f6;display:inline-block;border:1px solid #d1d5db;"></span> Cleaning</span>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem;">
        @forelse($tables as $table)
        @php
            $bg = match($table->status) {
                'available' => '#d1fae5',
                'occupied'  => '#fee2e2',
                'reserved'  => '#fef3c7',
                'cleaning'  => '#f3f4f6',
                default     => '#fff',
            };
            $border = match($table->status) {
                'available' => '#6ee7b7',
                'occupied'  => '#fca5a5',
                'reserved'  => '#fcd34d',
                'cleaning'  => '#d1d5db',
                default     => 'var(--color-border)',
            };
        @endphp
        <div style="background:{{ $bg }};border:2px solid {{ $border }};border-radius:8px;padding:1rem;display:flex;flex-direction:column;gap:0.5rem;">
            <div style="font-weight:700;font-size:1.1rem;">Table {{ $table->number }}</div>
            @if($table->name)
            <div style="font-size:0.8rem;color:var(--color-text-muted);">{{ $table->name }}</div>
            @endif
            <div style="font-size:0.8rem;">Seats: {{ $table->capacity }}</div>
            @if($table->status === 'occupied' && $table->currentOrder)
                <div style="font-size:0.8rem;font-weight:600;">KES {{ number_format($table->currentOrder->total, 2) }}</div>
                <a href="{{ route('tables.orders.show', $table->currentOrder) }}" class="btn btn-secondary" style="padding:0.35rem 0.6rem;font-size:0.8rem;text-align:center;">View Order</a>
            @else
                <form method="POST" action="{{ route('tables.open', $table) }}">
                    @csrf
                    <button class="btn btn-primary" style="padding:0.35rem 0.6rem;font-size:0.8rem;width:100%;">Open Order</button>
                </form>
            @endif
            <a href="{{ route('tables.qr', $table) }}" style="font-size:0.75rem;text-align:center;color:var(--color-text-muted);">📱 QR order code</a>
        </div>
        @empty
        <div style="grid-column:1/-1;text-align:center;color:var(--color-text-muted);padding:2rem;">
            No tables configured. <a href="{{ route('tables.manage') }}">Add tables</a>.
        </div>
        @endforelse
    </div>
</div>
@endsection
