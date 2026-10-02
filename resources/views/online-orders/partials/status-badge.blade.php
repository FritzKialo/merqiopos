@php
    $badgeClass = match($status) {
        'pending'    => 'badge-secondary',
        'paid'       => 'badge-success',
        'processing' => 'badge-warning',
        'shipped'    => 'badge-warning',
        'delivered'  => 'badge-success',
        'cancelled'  => 'badge-danger',
        default      => 'badge-secondary',
    };
@endphp
<span class="badge {{ $badgeClass }}">{{ ucfirst($status) }}</span>
