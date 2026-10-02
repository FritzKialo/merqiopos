@extends('layouts.app')
@section('title', 'Edit Invoice')
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">Edit {{ $invoice->invoice_number }}</h1>
        </div>
        <a href="{{ route('invoices.show', $invoice) }}" class="btn btn--outline">Cancel</a>
    </div>

    @if($errors->any())
        <div class="alert alert--error">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
    @endif

    <form method="POST" action="{{ route('invoices.update', $invoice) }}">
        @csrf
        @method('PUT')
        @include('invoices._form')
        <div class="table-card" style="margin-top:1rem;">
            <div class="card-body">
                <button type="submit" class="btn btn--primary">Update Invoice</button>
                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn--outline">Cancel</a>
            </div>
        </div>
    </form>
</div>
@endsection
