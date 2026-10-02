@extends('layouts.org')
@section('title', 'Select Active Stores')

@section('content')
<div class="page">

<div class="page-header">
    <div>
        <h1 class="page-title">Select Active Stores</h1>
        <p class="page-subtitle">
            {{-- planName() returns "Trial" while on trial — not useful here,
            since this message is explaining a concrete resource limit, so
            the real underlying plan name is shown instead. --}}
            Your plan (<strong>{{ $organization->planConfig()['name'] }}</strong>) allows
            {{ $limit }} {{ Str::plural('store', $limit) }}, but you currently have
            {{ $businesses->count() }}.
        </p>
    </div>
</div>

<div class="alert alert-warning" style="margin-bottom: var(--space-5);">
    Choose exactly {{ $limit }} {{ Str::plural('store', $limit) }} to keep fully active.
    The rest will become read-only — you'll still be able to view their data and reports,
    but not record new sales, edit stock, or make other changes there — until you upgrade
    your plan or remove a store.
</div>

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom: var(--space-5);">
        {{ $errors->first('business_ids') }}
    </div>
@endif

<form method="POST" action="{{ route('org.stores.select-active.update') }}">
    @csrf
    <div class="table-card">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 60px;">Keep</th>
                    <th>Store</th>
                    <th>Staff</th>
                </tr>
            </thead>
            <tbody>
                @foreach($businesses as $business)
                <tr>
                    <td data-label="Keep">
                        <input type="checkbox" name="business_ids[]" value="{{ $business->id }}"
                               {{ $business->is_default ? 'checked' : '' }}>
                    </td>
                    <td data-label="Store">
                        <div style="font-weight: 600;">{{ $business->name }}</div>
                        @if($business->city)
                            <div style="font-size: 0.78rem; color: var(--color-text-muted);">{{ $business->city }}</div>
                        @endif
                    </td>
                    <td data-label="Staff">
                        <span class="badge badge-secondary">{{ $business->users_count }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="form-actions" style="margin-top: var(--space-5);">
        <button type="submit" class="btn btn-primary">Save Selection</button>
        <a href="{{ route('settings.subscription') }}" class="btn btn-secondary">
            Upgrade Instead
        </a>
    </div>
</form>

</div>
@endsection
