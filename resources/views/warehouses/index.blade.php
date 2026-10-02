@extends('layouts.app')
@section('title', 'Warehouses')
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h1>Warehouses</h1>
    <a href="{{ route('warehouses.create') }}" class="btn btn-primary">+ New Warehouse</a>
</div>

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
@endif

@if($warehouses->isEmpty())
<div class="card">
    <div class="card-body text-muted" style="text-align:center;padding:60px;">
        <p>No warehouses yet. Create your first warehouse to start tracking multi-location stock.</p>
        <a href="{{ route('warehouses.create') }}" class="btn btn-primary" style="margin-top:12px;">Create Warehouse</a>
    </div>
</div>
@else
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
    @foreach($warehouses as $wh)
    <div class="card">
        <div class="card-body">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <h3 style="margin:0 0 4px;">{{ $wh->name }}</h3>
                    @if($wh->code)<span class="badge badge-secondary" style="font-size:0.78rem;">{{ $wh->code }}</span>@endif
                    @if($wh->is_default)<span class="badge badge-success" style="font-size:0.78rem;margin-left:4px;">Default</span>@endif
                </div>
                <form method="POST" action="{{ route('warehouses.destroy', $wh) }}" onsubmit="return confirm('Delete this warehouse?')">
                    @csrf @method('DELETE')
                    <button type="submit" style="background:none;border:none;color:var(--color-danger);cursor:pointer;font-size:0.85rem;">Delete</button>
                </form>
            </div>
            @if($wh->location)
            <div class="text-muted" style="font-size:0.85rem;margin-top:8px;">{{ $wh->location }}</div>
            @endif
            <div class="text-muted" style="display:flex;gap:16px;margin-top:12px;font-size:0.85rem;">
                <span><strong>{{ $wh->stock->count() }}</strong> SKUs</span>
                <span><strong>{{ number_format($wh->stock->sum('quantity'), 0) }}</strong> units</span>
            </div>
            <a href="{{ route('warehouses.show', $wh) }}" class="btn btn-secondary" style="margin-top:12px;width:100%;text-align:center;display:block;">View Stock</a>
        </div>
    </div>
    @endforeach
</div>
@endif
@endsection
