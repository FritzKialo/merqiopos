@extends('admin.layouts.app')
@section('title', 'Support — ' . ($conversation->business?->name ?? 'conversation'))
@section('subtitle', $conversation->user?->name)

@section('content')
<style>
.sp-grid{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:20px;align-items:start}
@media (max-width:1000px){.sp-grid{grid-template-columns:minmax(0,1fr)}}
.sp-thread{padding:18px;display:flex;flex-direction:column;gap:12px;max-height:60vh;overflow:auto;background:#f8fafc}
.sp-bub{max-width:78%;padding:10px 13px;border-radius:14px;font-size:14px;white-space:pre-wrap;word-break:break-word;line-height:1.45}
.sp-bub .meta{display:block;font-size:11px;opacity:.7;margin-top:5px}
.sp-tenant{align-self:flex-start;background:#fff;border:1px solid #e5e7eb}
.sp-staff{align-self:flex-end;background:#6366f1;color:#fff}
.sp-note{align-self:center;background:#fef3c7;border:1px dashed #f59e0b;font-size:13px;max-width:90%}
.sp-bub img{max-width:100%;border-radius:8px;margin-top:6px;display:block}
.sp-side dt{font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.04em;margin-top:10px}
.sp-side dd{margin:2px 0 0;font-size:13.5px;word-break:break-word}
.sp-canned{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px}
.sp-canned button{border:1px solid #d0d0d0;background:#fff;border-radius:999px;padding:4px 11px;font-size:12px;cursor:pointer}
.sp-canned button:hover{border-color:var(--admin-accent);color:var(--admin-accent)}
.sp-muted{color:var(--color-text-secondary);font-size:12px}
</style>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">{{ $conversation->business?->name ?? 'Unknown store' }}</h1>
        <p class="admin-page-subtitle">{{ $conversation->user?->name }} ({{ $conversation->role }}) · {{ $conversation->user?->email }}</p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('admin.support.index') }}" class="btn btn-secondary btn-sm">← Inbox</a>
        <form method="POST" action="{{ route('admin.support.status', $conversation) }}">
            @csrf
            <input type="hidden" name="status" value="{{ $conversation->status === 'resolved' ? 'open' : 'resolved' }}">
            <button class="btn btn-primary btn-sm" type="submit">{{ $conversation->status === 'resolved' ? 'Reopen' : 'Mark resolved' }}</button>
        </form>
    </div>
</div>

@if(session('success'))<div class="admin-alert admin-alert-success"><i class="ph-bold ph-check-circle"></i> {{ session('success') }}</div>@endif
@if(session('error'))<div class="admin-alert admin-alert-error"><i class="ph-bold ph-warning-circle"></i> {{ session('error') }}</div>@endif

<div class="sp-grid">
    <div>
        <div class="admin-panel" style="margin-bottom:16px;">
            <div class="sp-thread" id="spThread">
                @foreach($messages as $m)
                    <div class="sp-bub {{ $m->sender_type === 'tenant' ? 'sp-tenant' : ($m->sender_type === 'staff' ? 'sp-staff' : 'sp-note') }}">
                        @if($m->sender_type === 'note')<strong>Staff note</strong> — only staff can see this<br>@endif
                        {{ $m->body }}
                        @if($m->attachment_path)<a href="{{ route('admin.support.attachment', $m->id) }}" target="_blank"><img src="{{ route('admin.support.attachment', $m->id) }}" alt="Screenshot"></a>@endif
                        <span class="meta">{{ $m->sender_type === 'staff' ? ($m->sender?->name ?? 'Support') : ($m->sender_type === 'note' ? ($m->sender?->name ?? 'Staff') : ($conversation->user?->name ?? 'Tenant')) }} · {{ $m->created_at?->format('d M H:i') }}@if($m->page && $m->sender_type === 'tenant') · on {{ $m->page }}@endif</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="admin-panel">
            <div style="padding:16px;">
                @if($canned->isNotEmpty())
                <div class="sp-muted" style="margin-bottom:6px;">Saved replies — click to insert</div>
                <div class="sp-canned">
                    @foreach($canned as $cr)
                        <button type="button" data-body="{{ $cr->body }}">{{ $cr->title }}</button>
                    @endforeach
                </div>
                @endif
                <form method="POST" action="{{ route('admin.support.reply', $conversation) }}" enctype="multipart/form-data">
                    @csrf
                    <textarea name="body" id="spReply" rows="4" maxlength="4000" placeholder="Write your reply…" style="width:100%;padding:10px;border:1px solid #d0d0d0;border-radius:8px;font-size:14px;"></textarea>
                    <div style="display:flex;gap:10px;align-items:center;margin-top:10px;flex-wrap:wrap;">
                        <button class="btn btn-primary" type="submit">Send reply</button>
                        <label class="sp-muted" style="cursor:pointer;">Attach image <input type="file" name="image" accept="image/png,image/jpeg,image/webp" style="font-size:12px;"></label>
                    </div>
                </form>
                <details style="margin-top:16px;">
                    <summary class="sp-muted" style="cursor:pointer;">Add a private note (tenant never sees it)</summary>
                    <form method="POST" action="{{ route('admin.support.note', $conversation) }}" style="margin-top:8px;">
                        @csrf
                        <textarea name="body" rows="2" maxlength="2000" required style="width:100%;padding:8px;border:1px solid #f59e0b;border-radius:8px;font-size:13px;background:#fffbeb;"></textarea>
                        <button class="btn btn-secondary btn-sm" type="submit" style="margin-top:6px;">Save note</button>
                    </form>
                </details>
            </div>
        </div>
    </div>

    <div>
        <div class="admin-panel sp-side" style="margin-bottom:16px;">
            <div class="admin-panel-header"><h3 class="admin-panel-title">Store</h3></div>
            <dl style="padding:0 16px 14px;margin:0;">
                <dt>Store</dt><dd>{{ $conversation->business?->name }}</dd>
                <dt>Organization / plan</dt><dd>{{ $conversation->business?->organization?->name }} · {{ ucfirst($conversation->business?->organization?->subscription_plan ?? '—') }} ({{ $conversation->business?->organization?->status }})</dd>
                <dt>Owner</dt><dd>{{ $conversation->business?->organization?->owner?->name }} <span class="sp-muted">{{ $conversation->business?->organization?->owner?->email }}</span></dd>
                <dt>Phone</dt><dd>{{ $conversation->business?->phone ?: '—' }}</dd>
                <dt>Was on (first / latest)</dt><dd class="sp-muted">{{ $conversation->first_page ?: '—' }} / {{ $conversation->last_page ?: '—' }}</dd>
                <dt>Links</dt>
                <dd>
                    @if($conversation->business_id)
                    <a href="{{ route('admin.logs.activity', ['business_id' => $conversation->business_id]) }}" style="color:var(--color-primary);">Activity</a> ·
                    <a href="{{ route('admin.logs.errors', ['business_id' => $conversation->business_id, 'status' => 'all']) }}" style="color:var(--color-primary);">Errors</a> ·
                    <a href="{{ route('admin.businesses.show', $conversation->business_id) }}" style="color:var(--color-primary);">Store page</a>
                    @endif
                </dd>
            </dl>
        </div>

        <div class="admin-panel sp-side" style="margin-bottom:16px;">
            <div class="admin-panel-header"><h3 class="admin-panel-title">Recent problems (3 days)</h3></div>
            <div style="padding:8px 16px 14px;font-size:13px;">
                @forelse($errors as $e)
                    <div style="margin-bottom:8px;"><a href="{{ route('admin.logs.errors.show', $e->fingerprint) }}" style="color:#b91c1c;font-weight:600;text-decoration:none;">{{ class_basename($e->exception) }}</a> ×{{ $e->count }}<div class="sp-muted">{{ \Illuminate\Support\Str::limit($e->message, 90) }}</div></div>
                @empty
                @endforelse
                @forelse($problems as $p)
                    <div style="margin-bottom:8px;"><span class="lg-mono" style="font-family:monospace;font-size:12px;">{{ $p->route_name ?: $p->path }}</span> <span class="admin-badge admin-badge-gray">{{ $p->outcome }}</span><div class="sp-muted">{{ \Illuminate\Support\Str::limit($p->message, 90) }} · {{ $p->created_at?->diffForHumans() }}</div></div>
                @empty
                    @if($errors->isEmpty())<div class="sp-muted">Nothing has failed for this store recently.</div>@endif
                @endforelse
            </div>
        </div>

        <div class="admin-panel sp-side">
            <div class="admin-panel-header"><h3 class="admin-panel-title">Saved replies</h3></div>
            <div style="padding:8px 16px 14px;">
                @foreach($canned as $cr)
                    <form method="POST" action="{{ route('admin.support.canned.destroy', $cr) }}" style="display:flex;justify-content:space-between;gap:6px;font-size:13px;margin-bottom:4px;">
                        @csrf @method('DELETE')
                        <span>{{ $cr->title }}</span>
                        <button type="submit" style="border:0;background:none;color:#b91c1c;cursor:pointer;font-size:12px;" onclick="return confirm('Remove this saved reply?')">remove</button>
                    </form>
                @endforeach
                <details style="margin-top:8px;">
                    <summary class="sp-muted" style="cursor:pointer;">Add a saved reply</summary>
                    <form method="POST" action="{{ route('admin.support.canned.store') }}" style="margin-top:8px;">
                        @csrf
                        <input type="text" name="title" placeholder="Short title" maxlength="80" required style="width:100%;padding:6px 8px;border:1px solid #d0d0d0;border-radius:6px;margin-bottom:6px;font-size:13px;">
                        <textarea name="body" rows="3" placeholder="The reply text" maxlength="2000" required style="width:100%;padding:6px 8px;border:1px solid #d0d0d0;border-radius:6px;font-size:13px;"></textarea>
                        <button class="btn btn-secondary btn-sm" type="submit" style="margin-top:6px;">Save</button>
                    </form>
                </details>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var t = document.getElementById('spThread'); if (t) t.scrollTop = t.scrollHeight;
    var box = document.getElementById('spReply');
    document.querySelectorAll('.sp-canned button').forEach(function (b) {
        b.addEventListener('click', function () { box.value = (box.value ? box.value + '\n' : '') + b.dataset.body; box.focus(); });
    });
})();
</script>
@endsection
