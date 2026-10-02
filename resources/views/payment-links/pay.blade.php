<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Pay{{ $link ? ' - ' . $link->title : '' }}</title>
<style>body{font-family:sans-serif;max-width:480px;margin:40px auto;padding:20px;background:#f5f5f5;} .card{background:#fff;border-radius:8px;padding:32px;box-shadow:0 2px 8px rgba(0,0,0,.08);} .amount{font-size:2rem;font-weight:700;text-align:center;margin:16px 0;} .btn{background:#000;color:#fff;border:none;padding:14px 24px;border-radius:4px;width:100%;font-size:1rem;cursor:pointer;} input{width:100%;padding:10px;border:1px solid #ccc;border-radius:4px;margin-bottom:16px;box-sizing:border-box;font-size:1rem;}</style>
</head>
<body>
@if(!$link)
<div class="card"><div style="text-align:center;padding:20px;color:#721c24;">Payment link not found.</div></div>
@else
<div class="card">
    <h2 style="margin:0 0 4px;text-align:center;">{{ $business->name }}</h2>
    <p style="text-align:center;color:#888;margin:0 0 16px;">{{ $link->title }}</p>
    @if($link->description)<p style="color:#555;font-size:0.9rem;margin-bottom:16px;">{{ $link->description }}</p>@endif
    <div class="amount">KSh {{ number_format($link->amount, 2) }}</div>
    @if($link->status === 'paid')
    <div style="text-align:center;padding:20px;background:#d4edda;border-radius:4px;color:#155724;">&#10003; Payment received. Thank you!</div>
    @elseif($link->status === 'cancelled' || $link->isExpired())
    <div style="text-align:center;padding:20px;background:#f8d7da;border-radius:4px;color:#721c24;">&#10008; This payment link is no longer valid.</div>
    @else
    <div id="pay-form">
        <label style="font-weight:500;display:block;margin-bottom:6px;">M-Pesa Phone Number</label>
        <input type="tel" id="phone" placeholder="07XXXXXXXX" maxlength="12">
        <button class="btn" onclick="initiatePay()">Pay via M-Pesa</button>
        <p id="status-msg" style="text-align:center;margin-top:12px;color:#888;"></p>
    </div>
    @endif
</div>
@endif
<script>
function initiatePay() {
    const phone = document.getElementById('phone').value.trim();
    const msg = document.getElementById('status-msg');
    if (!phone) { msg.textContent = 'Please enter your phone number.'; return; }
    msg.textContent = 'Processing...';
    fetch('{{ $link ? route("pay.initiate", $link->token) : "" }}', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
        body: JSON.stringify({phone})
    }).then(r => r.json()).then(data => {
        msg.textContent = data.message || 'Check your phone for M-Pesa prompt.';
        if (data.success) msg.style.color = '#28a745';
    }).catch(() => { msg.textContent = 'Error processing payment. Please try again.'; });
}
</script>
</body>
</html>
