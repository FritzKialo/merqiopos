@extends('layouts.app')
@section('title', 'Budget Management')
@push('styles')
<style>
@media (max-width: 640px) {
    .budget-form-grid { grid-template-columns: 1fr !important; }
}
@media (min-width: 769px) {
    .budgets-table th, .budgets-table td { padding: 0.75rem 1rem; }
}
</style>
@endpush
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Budgets</h1>
            <p class="page-subtitle">{{ $months[$month] }} {{ $year }} — Budget vs Actual</p>
        </div>
        <form method="GET" style="display:flex;gap:0.5rem;align-items:center;">
            <select name="month" class="form-control">
                @foreach($months as $m => $name)
                    <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
            </select>
            <select name="year" class="form-control">
                @foreach($years as $y)
                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-secondary">View</button>
        </form>
    </div>

    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom:1rem;">{{ session('success') }}</div>
    @endif

    {{-- Budget Table --}}
    <div class="table-card" style="margin-bottom:1.5rem;">
        @if(empty($rows))
            <div style="padding:2rem;text-align:center;color:var(--color-text-muted);">No budgets set for this period. Add one below.</div>
        @else
        <table class="budgets-table" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:var(--color-surface);border-bottom:2px solid var(--color-border);">
                    <th style="text-align:left;font-size:0.8rem;text-transform:uppercase;">Category</th>
                    <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Budget</th>
                    <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Actual</th>
                    <th style="text-align:right;font-size:0.8rem;text-transform:uppercase;">Variance</th>
                    <th style="text-align:center;font-size:0.8rem;text-transform:uppercase;min-width:160px;">% Used</th>
                    <th style="text-align:center;font-size:0.8rem;text-transform:uppercase;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    @php
                        $pct      = $row['pct'];
                        $pctClass = $pct < 80 ? 'text-success' : ($pct <= 100 ? 'text-warn' : 'text-danger');
                        $barClass = $pct < 80 ? 'progress-bar-fill--full' : ($pct <= 100 ? 'progress-bar-fill--partial' : 'progress-bar-fill--over');
                    @endphp
                    <tr style="border-bottom:1px solid var(--color-border);">
                        <td data-label="Category" style="font-weight:500;">{{ $row['category']->name }}</td>
                        <td data-label="Budget" style="text-align:right;">
                            @if($row['budget'])
                                KSh {{ number_format($row['budgeted'], 2) }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td data-label="Actual" style="text-align:right;">KSh {{ number_format($row['actual'], 2) }}</td>
                        <td data-label="Variance" class="{{ $row['variance'] >= 0 ? 'text-success' : 'text-danger' }}" style="text-align:right;">
                            {{ $row['variance'] >= 0 ? '+' : '' }}KSh {{ number_format($row['variance'], 2) }}
                        </td>
                        <td data-label="% Used">
                            @if($row['budgeted'] > 0)
                            <div style="display:flex;align-items:center;gap:0.5rem;">
                                <div class="progress-bar" style="flex:1;">
                                    <div class="progress-bar-fill {{ $barClass }}" style="width:{{ min(100, $pct) }}%;"></div>
                                </div>
                                <span class="{{ $pctClass }}" style="font-size:0.8rem;font-weight:600;min-width:40px;">{{ $pct }}%</span>
                            </div>
                            @else
                                <span class="text-muted" style="font-size:0.8rem;">No budget</span>
                            @endif
                        </td>
                        <td data-label="Actions" style="text-align:center;">
                            @if($row['budget'])
                                <form method="POST" action="{{ route('budgets.destroy', $row['budget']->id) }}" style="display:inline;"
                                    onsubmit="return confirm('Delete this budget?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger" style="padding:0.25rem 0.5rem;font-size:0.75rem;">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background:var(--color-surface-2);font-weight:700;border-top:2px solid var(--color-border);">
                    <td data-label="Total">Total</td>
                    <td data-label="Budget" style="text-align:right;">KSh {{ number_format($totalBudget, 2) }}</td>
                    <td data-label="Actual" style="text-align:right;">KSh {{ number_format($totalActual, 2) }}</td>
                    <td data-label="Variance" class="{{ ($totalBudget - $totalActual) >= 0 ? 'text-success' : 'text-danger' }}" style="text-align:right;">
                        {{ ($totalBudget - $totalActual) >= 0 ? '+' : '' }}KSh {{ number_format($totalBudget - $totalActual, 2) }}
                    </td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
        @endif
    </div>

    {{-- Add Budget Form --}}
    <div class="table-card" style="padding:1.5rem;max-width:600px;">
        <h3 style="margin:0 0 1rem;font-family:var(--font-main);font-size:1rem;">Add / Update Budget</h3>
        <form method="POST" action="{{ route('budgets.store') }}">
            @csrf
            <input type="hidden" name="month" value="{{ $month }}">
            <input type="hidden" name="year" value="{{ $year }}">

            <div class="budget-form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                <div>
                    <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:0.25rem;">Category</label>
                    <select name="expense_category_id" class="form-control" required>
                        <option value="">Select category...</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    @error('expense_category_id')<p style="color:var(--color-danger);font-size:0.8rem;margin-top:0.25rem;">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:0.25rem;">Budget Amount (KSh)</label>
                    <input type="number" name="amount" class="form-control" min="0" step="0.01" placeholder="0.00" required>
                    @error('amount')<p style="color:var(--color-danger);font-size:0.8rem;margin-top:0.25rem;">{{ $message }}</p>@enderror
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Save Budget</button>
        </form>
    </div>
</div>
@endsection
