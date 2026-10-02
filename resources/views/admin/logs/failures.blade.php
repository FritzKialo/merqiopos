@extends('admin.layouts.app')
@section('title', 'All failures — Developer Logs')
@section('subtitle', 'Errors, refused actions, failed payments, jobs and messages in one list')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">All failures</h1>
        <p class="admin-page-subtitle">One feed from every source: crashes, rejected or refused actions, failed sign-ins, background jobs, KRA, M-Pesa and campaign messages.</p>
    </div>
</div>
@include('admin.logs._tabs')

<div class="admin-chip-row" style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
    <a href="{{ route('admin.logs.failures', ['days' => $days]) }}" class="lg-chip {{ request('source') ? '' : 'active' }}">Everything</a>
    @foreach($counts as $source => $n)
        <a href="{{ route('admin.logs.failures', ['days' => $days, 'source' => $source]) }}" class="lg-chip {{ request('source') === $source ? 'active' : '' }}">{{ $source }} <span style="opacity:.7">{{ $n }}</span></a>
    @endforeach
    <span style="margin-left:auto;display:flex;gap:6px;align-items:center;" class="lg-muted">
        Last
        @foreach([1, 7, 30] as $d)
            <a href="{{ route('admin.logs.failures', ['days' => $d, 'source' => request('source')]) }}" class="lg-chip {{ $days === $d ? 'active' : '' }}">{{ $d }} day{{ $d > 1 ? 's' : '' }}</a>
        @endforeach
    </span>
</div>

<div class="admin-panel">
    <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>When</th><th>Source</th><th>Store</th><th>What happened</th></tr></thead>
        <tbody>
        @forelse($items as $i)
            <tr>
                <td class="lg-muted" style="white-space:nowrap;">{{ $i['when']?->format('d M H:i') }}<div>{{ $i['when']?->diffForHumans() }}</div></td>
                <td><span class="admin-badge admin-badge-gray">{{ $i['source'] }}</span></td>
                <td>{{ $i['store'] ?? '—' }}</td>
                <td style="max-width:520px;word-break:break-word;">
                    @if($i['link'])<a href="{{ $i['link'] }}" style="color:var(--color-primary);text-decoration:none;">{{ $i['text'] }}</a>@else{{ $i['text'] }}@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="lg-muted" style="padding:22px;text-align:center;">Nothing failed in this period.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
