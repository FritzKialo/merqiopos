<div class="admin-chip-row" style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
    <style>
    .lg-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 13px;border-radius:999px;font-size:12.5px;font-weight:600;background:#fff;border:1px solid #d0d0d0;color:#444;text-decoration:none}
    .lg-chip:hover{border-color:var(--admin-accent);color:var(--admin-accent)}
    .lg-chip.active{background:var(--admin-accent-grad);border-color:transparent;color:#fff}
    .lg-mono{font-family:monospace;font-size:11.5px}
    .lg-muted{color:var(--color-text-secondary);font-size:12px}
    .lg-filter{display:flex;gap:8px;flex-wrap:wrap;align-items:end;padding:14px 16px;border-bottom:1px solid #eee}
    .lg-filter label{display:block;font-size:11px;font-weight:600;color:#666;margin-bottom:3px}
    .lg-filter input,.lg-filter select{padding:6px 9px;border:1px solid #d0d0d0;border-radius:6px;font-size:13px;background:#fff}
    .lg-pre{background:#0f172a;color:#e2e8f0;padding:14px;border-radius:8px;font-size:12px;overflow:auto;white-space:pre-wrap;word-break:break-all}
    .lg-bad{color:#b91c1c;font-weight:700}
    </style>
    <a href="{{ route('admin.logs.index') }}" class="lg-chip {{ request()->routeIs('admin.logs.index') ? 'active' : '' }}">Overview</a>
    <a href="{{ route('admin.logs.errors') }}" class="lg-chip {{ request()->routeIs('admin.logs.errors*') ? 'active' : '' }}">Errors</a>
    <a href="{{ route('admin.logs.failures') }}" class="lg-chip {{ request()->routeIs('admin.logs.failures') ? 'active' : '' }}">All failures</a>
    <a href="{{ route('admin.logs.activity') }}" class="lg-chip {{ request()->routeIs('admin.logs.activity') ? 'active' : '' }}">Activity</a>
</div>
