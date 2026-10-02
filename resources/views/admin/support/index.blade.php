@extends('admin.layouts.app')
@section('title', 'Support')
@section('subtitle', 'Messages from stores')

@section('content')
<style>
.sp-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 13px;border-radius:999px;font-size:12.5px;font-weight:600;background:#fff;border:1px solid #d0d0d0;color:#444;text-decoration:none}
.sp-chip:hover{border-color:var(--admin-accent);color:var(--admin-accent)}
.sp-chip.active{background:var(--admin-accent-grad);border-color:transparent;color:#fff}
.sp-chip .n{opacity:.8;font-weight:700}
.sp-unread{background:#ef4444;color:#fff;border-radius:999px;font-size:11px;font-weight:700;padding:1px 7px}
.sp-row td{vertical-align:top}
.sp-muted{color:var(--color-text-secondary);font-size:12px}
</style>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Support</h1>
        <p class="admin-page-subtitle">Messages from the chat bubble in every store. New messages also arrive by email.</p>
    </div>
</div>

@if(session('success'))<div class="admin-alert admin-alert-success"><i class="ph-bold ph-check-circle"></i> {{ session('success') }}</div>@endif

<div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;align-items:center;">
    <a href="{{ route('admin.support.index', ['filter' => 'open']) }}" class="sp-chip {{ $filter === 'open' ? 'active' : '' }}">Needs reply <span class="n">{{ $counts['open'] }}</span></a>
    <a href="{{ route('admin.support.index', ['filter' => 'unread']) }}" class="sp-chip {{ $filter === 'unread' ? 'active' : '' }}">Unread <span class="n">{{ $counts['unread'] }}</span></a>
    <a href="{{ route('admin.support.index', ['filter' => 'answered']) }}" class="sp-chip {{ $filter === 'answered' ? 'active' : '' }}">Answered <span class="n">{{ $counts['answered'] }}</span></a>
    <a href="{{ route('admin.support.index', ['filter' => 'resolved']) }}" class="sp-chip {{ $filter === 'resolved' ? 'active' : '' }}">Resolved <span class="n">{{ $counts['resolved'] }}</span></a>
    <a href="{{ route('admin.support.index', ['filter' => 'all']) }}" class="sp-chip {{ $filter === 'all' ? 'active' : '' }}">All</a>
    <form method="GET" style="margin-left:auto;display:flex;gap:6px;">
        <input type="hidden" name="filter" value="{{ $filter }}">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search store, person or message" style="padding:6px 10px;border:1px solid #d0d0d0;border-radius:6px;font-size:13px;min-width:230px;">
        <button class="btn btn-primary btn-sm" type="submit">Search</button>
    </form>
</div>

<div class="admin-panel">
    <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>Store</th><th>Person</th><th>Last message</th><th>Status</th><th>When</th></tr></thead>
        <tbody>
        @forelse($conversations as $c)
            <tr class="sp-row">
                <td>
                    <a href="{{ route('admin.support.show', $c) }}" style="font-weight:600;color:var(--color-primary);text-decoration:none;">{{ $c->business?->name ?? 'Unknown store' }}</a>
                    @if($c->unread_admin > 0)<span class="sp-unread">{{ $c->unread_admin }} new</span>@endif
                </td>
                <td>{{ $c->user?->name ?? '—' }}<div class="sp-muted">{{ $c->role }}</div></td>
                <td style="max-width:420px;word-break:break-word;">
                    <span class="sp-muted">{{ $c->last_message_by === 'staff' ? 'You: ' : '' }}</span>{{ $c->last_message_preview }}
                </td>
                <td>
                    @if($c->status === 'open')<span class="admin-badge admin-badge-red">Needs reply</span>
                    @elseif($c->status === 'answered')<span class="admin-badge admin-badge-indigo">Answered</span>
                    @else<span class="admin-badge admin-badge-green">Resolved</span>@endif
                </td>
                <td class="sp-muted" style="white-space:nowrap;">{{ $c->last_message_at?->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="sp-muted" style="padding:26px;text-align:center;">No conversations here.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div style="padding:12px 16px;">{{ $conversations->links() }}</div>
</div>
@endsection
