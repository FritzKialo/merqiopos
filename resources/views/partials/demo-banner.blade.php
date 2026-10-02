@auth
@php
    $dbOrg = auth()->user()->organization_id
        ? \Illuminate\Support\Facades\Cache::remember('demo-org:' . auth()->user()->organization_id, 600, fn () => (bool) \Illuminate\Support\Facades\DB::table('organizations')->where('id', auth()->user()->organization_id)->value('is_demo'))
        : false;
@endphp
@if($dbOrg)
<div class="demo-banner">
    <span class="demo-banner__badge">Demo</span>
    <p class="demo-banner__text">Sample data only — sending messages, payments and account changes are switched off. This store is deleted after 24 hours.</p>
    <form method="POST" action="{{ route('demo.exit') }}" class="demo-banner__form">
        @csrf
        <button type="submit" class="btn btn--primary btn--sm">Start your free trial</button>
    </form>
</div>
@endif
@endauth
