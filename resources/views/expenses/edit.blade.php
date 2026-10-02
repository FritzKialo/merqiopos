@extends('layouts.app')
@section('title', 'Edit Expense')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/expenses.css') }}">
@endpush

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">&#9998; Edit Expense</h1>
        <p class="page-subtitle">{{ $expense->title }}</p>
    </div>
    <a href="{{ route('expenses.index') }}" class="btn btn-outline">&#8592; Back</a>
</div>

<div class="form-card" style="max-width: 680px;">
    <form method="POST" action="{{ route('expenses.update', $expense) }}">
        @csrf
        @method('PUT')

        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label" for="title">Expense Title *</label>
                <input type="text" id="title" name="title"
                       class="form-control {{ $errors->has('title') ? 'is-invalid' : '' }}"
                       value="{{ old('title', $expense->title) }}"
                       required>
                @error('title')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="expense_category_id">Category</label>
                <select id="expense_category_id" name="expense_category_id" class="form-control">
                    <option value="">— Uncategorised —</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}"
                            {{ old('expense_category_id', $expense->expense_category_id) == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label" for="expense_date">Expense Date *</label>
                <input type="date" id="expense_date" name="expense_date"
                       class="form-control"
                       value="{{ old('expense_date', $expense->expense_date->format('Y-m-d')) }}"
                       max="{{ now()->format('Y-m-d') }}"
                       required>
            </div>

            <div class="form-group">
                <label class="form-label" for="amount">Amount (KSh) *</label>
                <input type="number" id="amount" name="amount"
                       class="form-control"
                       value="{{ old('amount', $expense->amount) }}"
                       step="0.01" min="0.01" required>
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label" for="payment_method">Payment Method *</label>
                <select id="payment_method" name="payment_method"
                        class="form-control"
                        onchange="toggleReference()">
                    @foreach(['cash', 'mpesa', 'bank_transfer'] as $method)
                        <option value="{{ $method }}"
                            {{ old('payment_method', $expense->payment_method) == $method ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $method)) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group" id="referenceGroup"
                 style="{{ old('payment_method', $expense->payment_method) != 'cash' ? '' : 'display:none;' }}">
                <label class="form-label" for="reference">Reference No.</label>
                <input type="text" id="reference" name="reference"
                       class="form-control"
                       value="{{ old('reference', $expense->reference) }}"
                       placeholder="e.g. QGH7JK2XYZ">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="description">Description</label>
            <textarea id="description" name="description"
                      class="form-control"
                      rows="2">{{ old('description', $expense->description) }}</textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">&#128190; Update Expense</button>
            <a href="{{ route('expenses.index') }}" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<div class="form-card" style="max-width: 680px; margin-top: 1.5rem;">
    @include('partials.attachments', ['modelType' => 'Expense', 'modelId' => $expense->id])
</div>

@endsection

@push('scripts')
    <script src="{{ asset('js/expenses.js') }}"></script>
@endpush
