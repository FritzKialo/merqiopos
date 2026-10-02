@extends('layouts.app')
@section('title', 'New Invoice')
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">New Invoice</h1>
        </div>
        <a href="{{ route('invoices.index') }}" class="btn btn--outline">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert--error">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
    @endif

    <form method="POST" action="{{ route('invoices.store') }}" id="invForm">
        @csrf
        @include('invoices._form', ['invoice' => null])
        <div class="table-card" style="margin-top:1rem;">
            <div class="card-body">
                <button type="submit" class="btn btn--primary">Create Invoice</button>
                <a href="{{ route('invoices.index') }}" class="btn btn--outline">Cancel</a>
            </div>
        </div>
    </form>
</div>
@endsection
