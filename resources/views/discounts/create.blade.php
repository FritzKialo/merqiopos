@extends('layouts.app')
@section('title', 'New Discount')

@section('content')

<div class="page-header">
    <h1 class="page-title">New Discount</h1>
    <a href="{{ route('discounts.index') }}" class="btn btn-outline">&#8592; Back</a>
</div>

<div class="form-card" style="max-width:640px;">
    <form method="POST" action="{{ route('discounts.store') }}">
        @csrf
        @include('discounts._form')
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Discount</button>
            <a href="{{ route('discounts.index') }}" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

@endsection
