@extends('layouts.app')
@section('title', 'Record Expense')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/expenses.css') }}">
@endpush

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">&#43; Record Expense</h1>
        <p class="page-subtitle">Log a new business expense</p>
    </div>
    <a href="{{ route('expenses.index') }}" class="btn btn-outline">&#8592; Back</a>
</div>

<div class="form-card" style="max-width: 680px;">
    <form method="POST" action="{{ route('expenses.store') }}">
        @csrf

        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label" for="title">Expense Title *</label>
                <input type="text" id="title" name="title"
                       class="form-control {{ $errors->has('title') ? 'is-invalid' : '' }}"
                       value="{{ old('title') }}"
                       placeholder="e.g. Monthly Rent"
                       required autofocus>
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
                            {{ old('expense_category_id') == $cat->id ? 'selected' : '' }}>
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
                       class="form-control {{ $errors->has('expense_date') ? 'is-invalid' : '' }}"
                       value="{{ old('expense_date', now()->format('Y-m-d')) }}"
                       max="{{ now()->format('Y-m-d') }}"
                       required>
                @error('expense_date')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="amount">Amount (KSh) *</label>
                <input type="number" id="amount" name="amount"
                       class="form-control {{ $errors->has('amount') ? 'is-invalid' : '' }}"
                       value="{{ old('amount') }}"
                       step="0.01" min="0.01"
                       placeholder="0.00"
                       required>
                @error('amount')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label" for="payment_method">Payment Method *</label>
                <select id="payment_method" name="payment_method"
                        class="form-control"
                        onchange="toggleReference()">
                    <option value="cash"
                        {{ old('payment_method', 'cash') == 'cash' ? 'selected' : '' }}>
                        Cash
                    </option>
                    <option value="mpesa"
                        {{ old('payment_method') == 'mpesa' ? 'selected' : '' }}>
                        M-Pesa
                    </option>
                    <option value="bank_transfer"
                        {{ old('payment_method') == 'bank_transfer' ? 'selected' : '' }}>
                        Bank Transfer
                    </option>
                </select>
            </div>

            <div class="form-group" id="referenceGroup"
                 style="{{ old('payment_method') != 'cash' ? '' : 'display:none;' }}">
                <label class="form-label" for="reference">Reference No.</label>
                <input type="text" id="reference" name="reference"
                       class="form-control"
                       value="{{ old('reference') }}"
                       placeholder="e.g. QGH7JK2XYZ">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="description">Description</label>
            <textarea id="description" name="description"
                      class="form-control"
                      rows="2"
                      placeholder="Optional details about this expense">{{ old('description') }}</textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">&#128190; Save Expense</button>
            <a href="{{ route('expenses.index') }}" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

@endsection

@push('scripts')
    <script src="{{ asset('js/expenses.js') }}"></script>
@endpush
