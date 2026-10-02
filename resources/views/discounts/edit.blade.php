@extends('layouts.app')
@section('title', 'Edit Discount')

@section('content')

<div class="page-header">
    <h1 class="page-title">Edit Discount</h1>
    <a href="{{ route('discounts.index') }}" class="btn btn-outline">&#8592; Back</a>
</div>

<div class="form-card" style="max-width:640px;">
    <form method="POST" action="{{ route('discounts.update', $discount) }}">
        @csrf @method('PUT')
        @include('discounts._form')
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update Discount</button>
            <a href="{{ route('discounts.index') }}" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

@endsection
