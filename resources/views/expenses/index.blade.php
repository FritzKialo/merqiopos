@extends('layouts.app')
@section('title', 'Expenses')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/expenses.css') }}?v={{ @filemtime(public_path('css/expenses.css')) ?: '1' }}">
@endpush

@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Expenses</h1>
            <p class="page-subtitle">Track overheads, costs, and operational spending.</p>
        </div>
        <div class="action-buttons">
            @role('owner','manager')
            @if(auth()->user()->currentBusiness()?->hasFeature('data_export'))
            <a href="{{ route('expenses.export') }}" class="btn btn--outline">Export CSV</a>
            @endif
            @endrole
            <a href="{{ route('expenses.create') }}" class="btn btn--primary">Record Expense</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom:1rem;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger" style="margin-bottom:1rem;">{{ session('error') }}</div>
    @endif

    {{-- KPI Strip --}}
    <div class="kpi-strip">
        <div class="kpi-item">
            <span class="kpi-value">KSh {{ number_format($stats['today'], 2) }}</span>
            <span class="kpi-label">Spend Today</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value">KSh {{ number_format($stats['this_month'], 2) }}</span>
            <span class="kpi-label">This Month</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value">KSh {{ number_format($stats['this_year'], 2) }}</span>
            <span class="kpi-label">Annual Total</span>
        </div>
        <div class="kpi-item">
            <span class="kpi-value">{{ $stats['total_count'] }}</span>
            <span class="kpi-label">Entries This Month</span>
        </div>
    </div>

    {{-- Categories manager (owner / overall manager / manager) --}}
    @role('owner','overall_manager','manager')
    <div class="table-card" style="padding:1.25rem; margin-bottom:1.5rem;">
        <h2 style="font-size:0.95rem; margin:0 0 0.75rem;">Expense Categories</h2>

        <form method="POST" action="{{ route('expenses.categories.store') }}"
              style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end; margin-bottom:1rem;">
            @csrf
            <div>
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Rent" value="{{ old('name') }}" required style="width:180px;">
                @error('name')<div style="color:var(--color-danger); font-size:0.8rem; margin-top:2px;">{{ $message }}</div>@enderror
            </div>
            <div style="flex:1; min-width:200px;">
                <label class="form-label">Description (optional)</label>
                <input type="text" name="description" class="form-control" placeholder="Notes" value="{{ old('description') }}">
            </div>
            <button type="submit" class="btn btn--primary">&#43; Add</button>
        </form>

        @if($categories->isEmpty())
            <p style="color:var(--color-text-muted); font-size:0.85rem; margin:0;">No categories yet. Add one above so expenses can be categorised.</p>
        @else
            <div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
                @foreach($categories as $cat)
                <span style="display:inline-flex; align-items:center; gap:8px; background:var(--color-surface); border:1px solid var(--color-border); border-radius:999px; padding:4px 6px 4px 14px; font-size:0.85rem;"
                      title="{{ $cat->description }}">
                    {{ $cat->name }}
                    <form method="POST" action="{{ route('expenses.categories.destroy', $cat) }}" style="display:inline; line-height:0;"
                          onsubmit="return confirm('Delete category &ldquo;{{ addslashes($cat->name) }}&rdquo;? Expenses using it will become uncategorised.')">
                        @csrf @method('DELETE')
                        <button type="submit" title="Delete category" style="border:none; background:none; cursor:pointer; color:var(--color-danger); font-weight:700; font-size:1rem; line-height:1; padding:0 2px;">&times;</button>
                    </form>
                </span>
                @endforeach
            </div>
        @endif
    </div>
    @endrole

    {{-- Toolbar --}}
    <form method="GET" action="{{ route('expenses.index') }}" id="filterForm">
        <div class="toolbar">
            <div class="toolbar-search">
                <input type="text" name="search" placeholder="Search entries..." value="{{ request('search') }}">
            </div>

            <select name="category_id" class="toolbar-select" onchange="this.form.submit()">
                <option value="">All Categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>

            <select name="payment_method" class="toolbar-select" onchange="this.form.submit()">
                <option value="">All Methods</option>
                <option value="cash" {{ request('payment_method') == 'cash' ? 'selected' : '' }}>Cash</option>
                <option value="mpesa" {{ request('payment_method') == 'mpesa' ? 'selected' : '' }}>M-Pesa</option>
                <option value="bank_transfer" {{ request('payment_method') == 'bank_transfer' ? 'selected' : '' }}>Bank</option>
            </select>

            <div style="display: flex; gap: 8px;">
                <input type="date" name="date_from" class="toolbar-select" value="{{ request('date_from') }}">
                <input type="date" name="date_to" class="toolbar-select" value="{{ request('date_to') }}">
            </div>

            @if(request()->hasAny(['search', 'category_id', 'payment_method', 'date_from', 'date_to']))
                <a href="{{ route('expenses.index') }}" class="btn btn--outline btn--sm">Clear</a>
            @endif
            <button type="submit" class="btn btn--primary btn--sm">Search</button>
        </div>
    </form>

    {{-- Expense List --}}
    <div class="table-section">
        @if($expenses->isEmpty())
            <div class="empty-state">
                <h3>No expenses found</h3>
                <p>Start recording your business costs to track them here.</p>
                <a href="{{ route('expenses.create') }}" class="btn btn--primary" style="margin-top: 1rem;">Record Expense</a>
            </div>
        @else
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Title &amp; Description</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Date</th>
                            <th>Recorded By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($expenses as $expense)
                        <tr>
                            <td data-label="Title">
                                <span style="font-weight: 700; color: var(--color-text);">{{ $expense->title }}</span>
                                @if($expense->description)
                                    <div style="font-size: 0.75rem; color: var(--color-text-muted);">{{ Str::limit($expense->description, 40) }}</div>
                                @endif
                            </td>
                            <td data-label="Category">
                                @if($expense->category)
                                    <span class="badge badge-neutral">{{ $expense->category->name }}</span>
                                @else
                                    <span style="color: var(--color-text-muted);">—</span>
                                @endif
                            </td>
                            <td data-label="Amount"><span class="expense-amount">KSh {{ number_format($expense->amount, 2) }}</span></td>
                            <td data-label="Method">
                                <span class="badge badge-neutral">{{ ucfirst(str_replace('_', ' ', $expense->payment_method)) }}</span>
                            </td>
                            <td data-label="Date" style="white-space:nowrap;">{{ $expense->expense_date->format('d M Y') }}</td>
                            <td data-label="Recorded By" style="color: var(--color-text-muted); font-size: 0.85rem;">{{ $expense->user->name }}</td>
                            <td data-label="Actions">
                                <div class="action-buttons">
                                    @role('owner','manager')
                                    <a href="{{ route('expenses.edit', $expense) }}" class="btn btn--outline btn--sm">Edit</a>
                                    <form method="POST" action="{{ route('expenses.destroy', $expense) }}" onsubmit="return confirm('Delete this expense?')" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm">Delete</button>
                                    </form>
                                    @endrole
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $expenses->links('vendor.pagination.custom') }}
        @endif
    </div>
</div>
@endsection
