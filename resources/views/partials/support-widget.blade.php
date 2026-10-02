@auth
@if(! auth()->user()->isSuperAdmin() && ! request()->attributes->get('is_demo'))
@php $swFirstName = explode(' ', trim(auth()->user()->name))[0] ?? ''; @endphp
{{-- Support chat bubble: talks to SupportChatController. Polling, not websockets (shared hosting). --}}
<div id="supportWidget" aria-live="polite">
    <style>
    #supportWidget { position: fixed; right: 18px; bottom: 18px; z-index: 9000; font-family: inherit; }
    body.kiosk-mode #supportWidget { display: none !important; }
    #supportBubble { width: 56px; height: 56px; border-radius: 50%; border: 0; background: var(--color-primary, #0f766e); color: #fff; cursor: pointer; box-shadow: 0 6px 20px rgba(0,0,0,.28); display: flex; align-items: center; justify-content: center; position: relative; }
    #supportBubble svg { width: 26px; height: 26px; }
    #supportBadge { position: absolute; top: -4px; right: -4px; min-width: 20px; height: 20px; padding: 0 5px; border-radius: 999px; background: #ef4444; color: #fff; font-size: 11px; font-weight: 700; display: none; align-items: center; justify-content: center; }
    #supportPanel { position: absolute; right: 0; bottom: 70px; width: 350px; max-width: calc(100vw - 24px); height: 480px; max-height: calc(100vh - 110px); background: var(--color-surface, #fff); color: var(--color-text, #111); border: 1px solid var(--color-border, #ddd); border-radius: 14px; box-shadow: 0 12px 40px rgba(0,0,0,.3); display: none; flex-direction: column; overflow: hidden; }
    #supportPanel.open { display: flex; }
    .sw-head { background: var(--color-primary, #0f766e); color: #fff; padding: 12px 14px; display: flex; align-items: center; justify-content: space-between; }
    .sw-head strong { font-size: 15px; } .sw-head small { display: block; opacity: .85; font-size: 12px; margin-top: 2px; }
    .sw-head button { background: none; border: 0; color: #fff; font-size: 22px; cursor: pointer; line-height: 1; min-width: 36px; min-height: 36px; }
    .sw-list { flex: 1; overflow-y: auto; padding: 12px; display: flex; flex-direction: column; gap: 8px; background: var(--color-surface-2, #f5f7fa); }
    .sw-msg { max-width: 82%; padding: 8px 11px; border-radius: 14px; font-size: 14px; line-height: 1.4; white-space: pre-wrap; word-break: break-word; }
    .sw-me { align-self: flex-end; background: var(--color-primary, #0f766e); color: #fff; border-bottom-right-radius: 4px; }
    .sw-them { align-self: flex-start; background: var(--color-surface, #fff); border: 1px solid var(--color-border, #ddd); border-bottom-left-radius: 4px; }
    .sw-msg small { display: block; font-size: 10.5px; opacity: .65; margin-top: 3px; }
    .sw-msg img { max-width: 100%; border-radius: 8px; margin-top: 5px; display: block; cursor: zoom-in; }
    .sw-empty { margin: auto; text-align: center; font-size: 13.5px; color: var(--color-text-muted, #666); padding: 10px 16px; line-height: 1.5; }
    .sw-form { border-top: 1px solid var(--color-border, #ddd); padding: 8px; display: flex; flex-direction: column; gap: 6px; background: var(--color-surface, #fff); }
    .sw-row { display: flex; gap: 6px; align-items: flex-end; }
    .sw-form textarea { flex: 1; resize: none; border: 1px solid var(--color-border, #ccc); border-radius: 10px; padding: 8px 10px; font: inherit; font-size: 14px; max-height: 96px; min-height: 40px; background: var(--color-surface, #fff); color: inherit; }
    .sw-btn { border: 0; border-radius: 10px; min-width: 42px; min-height: 42px; cursor: pointer; font-weight: 600; }
    .sw-send { background: var(--color-primary, #0f766e); color: #fff; padding: 0 14px; }
    .sw-send[disabled] { opacity: .5; cursor: default; }
    .sw-attach { background: transparent; border: 1px solid var(--color-border, #ccc); color: inherit; }
    .sw-file { font-size: 12px; color: var(--color-text-muted, #666); display: none; align-items: center; gap: 6px; }
    .sw-file button { border: 0; background: none; color: #ef4444; cursor: pointer; font-size: 14px; }
    .sw-err { color: #ef4444; font-size: 12px; display: none; }
    @media (max-width: 480px) { #supportWidget { right: 12px; bottom: 12px; } #supportPanel { position: fixed; right: 8px; left: 8px; bottom: 76px; width: auto; height: min(70vh, 520px); } }
    @media print { #supportWidget { display: none !important; } }
    </style>

    <button type="button" id="supportBubble" aria-label="Chat with support" title="Chat with support">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <span id="supportBadge"></span>
    </button>

    <div id="supportPanel" role="dialog" aria-label="Support chat">
        <div class="sw-head">
            <div><strong>Support</strong><small>Ask us anything — we reply here</small></div>
            <button type="button" id="supportClose" aria-label="Close">&times;</button>
        </div>
        <div class="sw-list" id="supportList"></div>
        <form class="sw-form" id="supportForm" autocomplete="off">
            <div class="sw-err" id="supportErr"></div>
            <div class="sw-file" id="supportFile"><span id="supportFileName"></span><button type="button" id="supportFileClear" aria-label="Remove screenshot">&times;</button></div>
            <div class="sw-row">
                <label class="sw-btn sw-attach" style="display:flex;align-items:center;justify-content:center;" title="Attach a screenshot">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                    <input type="file" id="supportImage" accept="image/png,image/jpeg,image/webp" style="display:none;">
                </label>
                <textarea id="supportBody" rows="1" maxlength="2000" placeholder="Type your message…"></textarea>
                <button type="submit" class="sw-btn sw-send" id="supportSend">Send</button>
            </div>
        </form>
    </div>

    <script>
    (function () {
        var URLS = { thread: @json(route('support.thread')), send: @json(route('support.send')), unread: @json(route('support.unread')) };
        var CSRF = @json(csrf_token());
        var FIRST = @json($swFirstName);
        var panel = document.getElementById('supportPanel'), list = document.getElementById('supportList'), badge = document.getElementById('supportBadge');
        var body = document.getElementById('supportBody'), form = document.getElementById('supportForm'), sendBtn = document.getElementById('supportSend');
        var img = document.getElementById('supportImage'), fileBox = document.getElementById('supportFile'), fileName = document.getElementById('supportFileName'), err = document.getElementById('supportErr');
        var lastId = 0, isOpen = false, pollTimer = null, unreadTimer = null;

        function setBadge(n) { badge.textContent = n > 9 ? '9+' : n; badge.style.display = n > 0 ? 'flex' : 'none'; }
        function showErr(m) { err.textContent = m || ''; err.style.display = m ? 'block' : 'none'; }
        function atBottom() { return list.scrollHeight - list.scrollTop - list.clientHeight < 60; }

        function render(m) {
            var el = document.createElement('div'); el.className = 'sw-msg ' + (m.mine ? 'sw-me' : 'sw-them');
            var t = document.createElement('span'); t.textContent = m.body === '(screenshot)' || m.body === '(image)' ? '' : m.body; el.appendChild(t);
            if (m.image) { var a = document.createElement('img'); a.src = m.image; a.alt = 'Screenshot'; a.addEventListener('click', function () { window.open(m.image, '_blank'); }); el.appendChild(a); }
            var s = document.createElement('small'); s.textContent = (m.mine ? 'You' : 'Support') + ' · ' + m.time; el.appendChild(s);
            list.appendChild(el);
        }
        function empty() {
            if (list.children.length) return;
            var e = document.createElement('div'); e.className = 'sw-empty'; e.id = 'supportEmpty';
            e.textContent = 'Hi' + (FIRST ? ' ' + FIRST : '') + '! Tell us what you need help with. Describe what you were trying to do, and attach a screenshot if you can.';
            list.appendChild(e);
        }
        function add(messages) {
            if (!messages.length) return;
            var stick = atBottom(); var e = document.getElementById('supportEmpty'); if (e) e.remove();
            messages.forEach(function (m) { if (m.id > lastId) { render(m); lastId = m.id; } });
            if (stick) list.scrollTop = list.scrollHeight;
        }

        function load(initial) {
            var url = URLS.thread + '?mark=1' + (initial ? '' : '&after=' + lastId);
            fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
                if (!d) return;
                if (initial) { list.innerHTML = ''; lastId = 0; }
                add(d.messages); if (initial) { empty(); list.scrollTop = list.scrollHeight; }
                setBadge(0);
            }).catch(function () {});
        }
        function checkUnread() {
            if (isOpen || document.hidden) return;
            fetch(URLS.unread, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) { if (d) setBadge(d.unread); }).catch(function () {});
        }
        function startPolling() { stopPolling(); pollTimer = setInterval(function () { if (!document.hidden) load(false); }, 5000); }
        function stopPolling() { if (pollTimer) clearInterval(pollTimer); pollTimer = null; }

        function open() { isOpen = true; panel.classList.add('open'); load(true); startPolling(); setTimeout(function () { body.focus(); }, 50); }
        function close() { isOpen = false; panel.classList.remove('open'); stopPolling(); checkUnread(); }
        document.getElementById('supportBubble').addEventListener('click', function () { isOpen ? close() : open(); });
        document.getElementById('supportClose').addEventListener('click', close);

        img.addEventListener('change', function () {
            var f = img.files[0]; showErr('');
            if (f && f.size > 3 * 1024 * 1024) { showErr('That image is over 3 MB. Please attach a smaller screenshot.'); img.value = ''; f = null; }
            fileBox.style.display = f ? 'flex' : 'none'; fileName.textContent = f ? f.name : '';
        });
        document.getElementById('supportFileClear').addEventListener('click', function () { img.value = ''; fileBox.style.display = 'none'; });
        body.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });
        body.addEventListener('input', function () { body.style.height = 'auto'; body.style.height = Math.min(body.scrollHeight, 96) + 'px'; });

        form.addEventListener('submit', function (e) {
            e.preventDefault(); showErr('');
            var text = body.value.trim();
            if (!text && !img.files[0]) return;
            var fd = new FormData(); fd.append('body', text); fd.append('page', location.pathname);
            if (img.files[0]) fd.append('image', img.files[0]);
            sendBtn.disabled = true;
            fetch(URLS.send, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
                .then(function (x) {
                    if (!x.ok) { showErr((x.d && (x.d.error || x.d.message)) || 'Could not send. Please try again.'); return; }
                    body.value = ''; body.style.height = 'auto'; img.value = ''; fileBox.style.display = 'none';
                    add([x.d.message]);
                })
                .catch(function () { showErr('No connection. Please try again.'); })
                .then(function () { sendBtn.disabled = false; });
        });

        checkUnread(); unreadTimer = setInterval(checkUnread, 45000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) { isOpen ? load(false) : checkUnread(); } });
    })();
    </script>
</div>
@endif
@endauth
