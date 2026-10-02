{{-- QR alternative to the STK push above — didn't get the prompt, or
checking out on a desktop where scanning is easier than typing a phone
number? Settles through the same channel as a manually-typed till
payment, matched by this order's reference — see StoreController::mpesaQr(). --}}
<div id="qrSection" style="margin:16px 0;">
    <button type="button" id="shopQrBtn" onclick="generateShopQr('{{ route('shop.order.mpesa-qr', [$business->store_slug, $order->reference]) }}')" class="btn btn-outline" style="margin-bottom:12px;">
        Or Scan QR Code Instead
    </button>
    <div id="shopQrDisplay" style="display:none;">
        <img id="shopQrImage" src="" alt="M-Pesa QR Code" style="max-width:220px; width:100%; margin:0 auto 8px; border-radius:8px; border:1px solid #e5e7eb; display:block;">
        <p style="color:#6b7280; font-size:.85rem;">Amount: <strong id="shopQrAmount"></strong></p>
        <p style="color:#6b7280; font-size:.8rem;">Open M-Pesa &rarr; Lipa na M-Pesa &rarr; Scan QR, then tap Refresh Status above once paid.</p>
    </div>
    <div id="shopQrError" style="display:none; color:#b91c1c; font-size:.85rem; margin-top:8px;"></div>
</div>

@once
@push('scripts')
<script>
function generateShopQr(url) {
    const btn = document.getElementById('shopQrBtn');
    const err = document.getElementById('shopQrError');
    err.style.display = 'none';
    btn.disabled = true;
    btn.textContent = 'Generating…';

    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('shopQrImage').src = 'data:image/png;base64,' + data.qr_code;
                document.getElementById('shopQrAmount').textContent = 'KSh ' + Number(data.amount).toLocaleString();
                document.getElementById('shopQrDisplay').style.display = 'block';
                btn.style.display = 'none';
            } else {
                err.textContent = data.message || 'Could not generate QR code.';
                err.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Or Scan QR Code Instead';
            }
        })
        .catch(() => {
            err.textContent = 'Network error. Please try again.';
            err.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Or Scan QR Code Instead';
        });
}
</script>
@endpush
@endonce
