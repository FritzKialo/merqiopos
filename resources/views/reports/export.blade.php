@extends('layouts.app')
@section('title', 'Export Data')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/reports.css') }}?v={{ @filemtime(public_path('css/reports.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Export Data</h1>
        <p class="page-subtitle">Download your business data as CSV files for external analysis.</p>
    </div>
    <a href="{{ route('reports.index') }}" class="btn btn-outline">Back to Reports</a>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.5rem;margin-top:1rem;">

    {{-- Sales --}}
    <div class="report-card" style="display:block;text-decoration:none;">
        <div class="report-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div>
        <h3>Sales</h3>
        <p>All sales transactions including payment status, totals, and customer info.</p>
        <a href="{{ route('sales.export') }}" class="btn btn-primary" style="margin-top:1rem;width:100%;text-align:center;">
            Download CSV
        </a>
    </div>

    {{-- Inventory --}}
    <div class="report-card" style="display:block;text-decoration:none;">
        <div class="report-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></div>
        <h3>Inventory</h3>
        <p>All products with stock levels, prices, SKUs, barcodes, and categories.</p>
        <a href="{{ route('inventory.export') }}" class="btn btn-primary" style="margin-top:1rem;width:100%;text-align:center;">
            Download CSV
        </a>
    </div>

    {{-- Customers --}}
    <div class="report-card" style="display:block;text-decoration:none;">
        <div class="report-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
        <h3>Customers</h3>
        <p>All customer records including contact details and outstanding balances.</p>
        <a href="{{ route('customers.export') }}" class="btn btn-primary" style="margin-top:1rem;width:100%;text-align:center;">
            Download CSV
        </a>
    </div>

    {{-- Expenses --}}
    <div class="report-card" style="display:block;text-decoration:none;">
        <div class="report-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em; height:1em;"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
        <h3>Expenses</h3>
        <p>All recorded expenses with categories, payment methods, and dates.</p>
        <a href="{{ route('expenses.export') }}" class="btn btn-primary" style="margin-top:1rem;width:100%;text-align:center;">
            Download CSV
        </a>
    </div>

</div>
</div>{{-- end .page --}}
@endsection
