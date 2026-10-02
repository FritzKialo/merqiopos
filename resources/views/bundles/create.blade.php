@extends('layouts.app')
@section('title', 'New Bundle')

@section('content')

<div class="page-header">
    <h1 class="page-title">New Bundle</h1>
    <a href="{{ route('bundles.index') }}" class="btn btn-outline">&#8592; Back</a>
</div>

<div class="form-card" style="max-width:780px;">
    <form method="POST" action="{{ route('bundles.store') }}">
        @csrf
        @include('bundles._form')
        <div class="form-actions" style="margin-top:var(--space-lg);">
            <button type="submit" class="btn btn-primary">Create Bundle</button>
            <a href="{{ route('bundles.index') }}" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

@endsection
