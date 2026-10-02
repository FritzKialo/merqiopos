@extends('layouts.app')
@section('title', 'Home')

{{-- Kiosk mode — this is the very first screen after login, so it shouldn't
     have the sidebar/topbar around it either (see body.kiosk-mode in
     sidebar.css, shared with the POS till). No top bar or greeting text of
     its own either — just the tiles, Logout being one of them. --}}
@section('body-class', 'kiosk-mode')

@push('styles')
<style>
.home-shell {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: var(--color-background);
}
.home-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    /* NOT align-items:center — that lets a flex child size to its own
       max-content instead of the container's actual width, which fooled
       the grid below into computing 5 auto-fit columns (its "ideal" size)
       regardless of viewport width, overflowing it. Stretching the child
       to the real available width first is what lets auto-fit wrap
       correctly; justify-content here still centers everything vertically,
       and the grid's own justify-content centers its columns horizontally
       within that full width. */
    justify-content: center;
    padding: 32px 20px;
    box-sizing: border-box;
}
.home-tile-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 150px));
    gap: 18px;
    justify-content: center;
    width: 100%;
    max-width: 1000px;
    margin: 0 auto;
}
.home-tile {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    aspect-ratio: 1 / 1;
    border-radius: 18px;
    border: none;
    text-decoration: none;
    color: #fff;
    font-family: var(--font-heading);
    font-weight: 700;
    font-size: 13.5px;
    text-align: center;
    padding: 10px;
    box-sizing: border-box;
    cursor: pointer;
    box-shadow: 0 2px 0 rgba(0,0,0,.12), 0 8px 18px rgba(0,0,0,.14);
    transition: transform .12s ease, box-shadow .12s ease;
}
.home-tile:hover { transform: translateY(-3px); box-shadow: 0 4px 0 rgba(0,0,0,.14), 0 14px 24px rgba(0,0,0,.18); }
.home-tile:active { transform: translateY(0); box-shadow: 0 1px 0 rgba(0,0,0,.12), 0 4px 10px rgba(0,0,0,.14); }
.home-tile svg { width: 34px; height: 34px; }

/* Solid, saturated tile colors — a physical POS terminal's home screen
   (the reference for this layout) uses one flat color per tile, not the
   app's usual muted palette, so each function reads as its own "app icon"
   at a glance. */
.tile-slate   { background: #475569; }
.tile-teal    { background: #0f766e; }
.tile-indigo  { background: #4f46e5; }
.tile-violet  { background: #7c3aed; }
.tile-amber   { background: #d97706; }
.tile-rose    { background: #e11d48; }
.tile-blue    { background: #2563eb; }
.tile-orange  { background: #ea580c; }
.tile-pink    { background: #db2777; }
.tile-emerald { background: #059669; }
.tile-gray    { background: #52525b; }
.tile-danger  { background: #b91c1c; }

/* The Logout form needs to sit in the grid exactly like the <a> tiles —
   display:contents makes it invisible to layout so its button behaves as
   the actual grid item. */
.home-tile-form { display: contents; }

@media (max-width: 480px) {
    .home-tile-grid { grid-template-columns: repeat(auto-fit, minmax(100px, 120px)); gap: 12px; }
    .home-tile svg { width: 28px; height: 28px; }
    .home-tile { font-size: 12px; border-radius: 14px; }
}
</style>
@endpush

@section('content')
<div class="home-shell">

    <div class="home-body">
        <div class="home-tile-grid">
            @foreach($tiles as $tile)
            <a href="{{ route($tile['route']) }}" class="home-tile tile-{{ $tile['color'] }}">
                @switch($tile['icon'])
                    @case('grid')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                        @break
                    @case('cart')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        @break
                    @case('package')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                        @break
                    @case('users')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        @break
                    @case('tool')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                        @break
                    @case('receipt')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        @break
                    @case('bar-chart')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        @break
                    @case('truck')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                        @break
                    @case('tag')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                        @break
                    @case('staff')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>
                        @break
                    @case('settings')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        @break
                @endswitch
                {{ $tile['label'] }}
            </a>
            @endforeach

            {{-- Logout — a tile like everything else here, not a separate
                 top-bar button (there is no top bar on this page). --}}
            <form method="POST" action="{{ route('logout') }}" class="home-tile-form">
                @csrf
                <button type="submit" class="home-tile tile-danger">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Logout
                </button>
            </form>
        </div>
    </div>

</div>
@endsection
