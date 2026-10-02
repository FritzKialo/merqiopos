{{--
    Shared footer for customer-facing store emails — the business's own
    contact details, matching what invoice.blade.php already had, now
    reused everywhere instead of duplicated per file. Expects $business.
--}}
<div class="footer">
    <p><strong>{{ $business->name }}</strong></p>
    @if($business->phone)
        <p>{{ $business->phone }}</p>
    @endif
    @if($business->email)
        <p>{{ $business->email }}</p>
    @endif
    @if($business->address)
        <p>{{ $business->address }}@if($business->city), {{ $business->city }}@endif</p>
    @endif
    <p style="margin-top: 12px;">&copy; {{ date('Y') }} {{ $business->name }}. All rights reserved.</p>
    <p style="margin-top: 4px; opacity: 0.7;">Powered by Merqio POS</p>
</div>
