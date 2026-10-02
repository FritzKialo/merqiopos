<div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;padding:12px;background:#f0f7ff;border-radius:4px;border:1px solid #cce4f7;">
    <span style="display:inline-flex;color:#555;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></span>
    <input type="text" id="barcode-input" class="form-control" placeholder="Scan barcode or type product name/SKU..." autocomplete="off" style="flex:1;" autofocus>
    <div id="barcode-status" style="min-width:120px;font-size:0.85rem;color:#888;"></div>
</div>
<script>
(function() {
    const inp = document.getElementById('barcode-input');
    const status = document.getElementById('barcode-status');
    let debounce;
    inp.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); doLookup(inp.value.trim()); }
    });
    inp.addEventListener('input', function() {
        clearTimeout(debounce);
        debounce = setTimeout(() => { if (inp.value.length >= 2) doLookup(inp.value.trim()); }, 400);
    });
    function doLookup(q) {
        if (!q) return;
        status.textContent = 'Searching...';
        fetch('/api/products/lookup?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                if (data.found) {
                    status.textContent = '✓ Found: ' + data.name;
                    status.style.color = '#28a745';
                    document.dispatchEvent(new CustomEvent('barcode-scanned', { detail: data }));
                    inp.value = '';
                    setTimeout(() => { status.textContent = ''; status.style.color = '#888'; }, 2000);
                } else {
                    status.textContent = '✗ Not found';
                    status.style.color = '#dc3545';
                    setTimeout(() => { status.textContent = ''; status.style.color = '#888'; }, 2000);
                }
            });
    }
})();
</script>
