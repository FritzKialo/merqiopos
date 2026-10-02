@extends('layouts.app')
@section('title', $stockTransfer->transfer_number)
@section('content')
<div class="page">
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $stockTransfer->transfer_number }}</h1>
            @php $sc = ['pending'=>'badge--gray','approved'=>'badge--gray','dispatched'=>'badge--gray','received'=>'badge--green','cancelled'=>'badge--red']; @endphp
            <p class="page-subtitle">
                <span class="badge {{ $sc[$stockTransfer->status] ?? 'badge--gray' }}">{{ ucfirst($stockTransfer->status) }}</span>
                &nbsp; {{ $stockTransfer->fromBusiness?->name }} &rarr; {{ $stockTransfer->toBusiness?->name }}
            </p>
        </div>
        <a href="{{ route('org.transfers.index') }}" class="btn btn--outline">Back</a>
    </div>

    @if(session('success')) <div class="alert alert--success">{{ session('success') }}</div> @endif
    @if(session('error'))   <div class="alert alert--error">{{ session('error') }}</div> @endif

    {{-- Timeline --}}
    <div class="table-card" style="margin-bottom:1.5rem;">
        <div class="card-body">
            <div style="display:flex;gap:0;position:relative;">
                @foreach(['pending','approved','dispatched','received'] as $step)
                @php
                    $statusOrder = ['pending'=>0,'approved'=>1,'dispatched'=>2,'received'=>3,'cancelled'=>-1];
                    $current = $statusOrder[$stockTransfer->status] ?? 0;
                    $stepOrder = $statusOrder[$step] ?? 0;
                    $done = $current >= $stepOrder && $stockTransfer->status !== 'cancelled';
                @endphp
                <div style="flex:1;text-align:center;position:relative;">
                    <div style="width:28px;height:28px;border-radius:50%;margin:0 auto;background:{{ $done ? 'var(--primary)' : 'var(--border)' }};display:flex;align-items:center;justify-content:center;color:{{ $done ? '#fff' : 'var(--text-muted)' }};font-size:12px;font-weight:700;">{{ $loop->index + 1 }}</div>
                    <div style="font-size:12px;margin-top:4px;text-transform:capitalize;color:{{ $done ? 'var(--primary)' : 'var(--text-muted)' }};">{{ $step }}</div>
                    @if(!$loop->last)
                        <div style="position:absolute;top:14px;left:calc(50% + 14px);width:calc(100% - 28px);height:2px;background:{{ $done && $current > $stepOrder ? 'var(--primary)' : 'var(--border)' }};"></div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div style="display:flex;gap:0.75rem;margin-bottom:1.5rem;flex-wrap:wrap;">
        @if($stockTransfer->status === 'pending')
        <form method="POST" action="{{ route('org.transfers.approve', $stockTransfer) }}">
            @csrf @method('PATCH')
            <button class="btn btn--success">Approve</button>
        </form>
        @endif

        @if(in_array($stockTransfer->status, ['pending','approved','dispatched']))
        <form method="POST" action="{{ route('org.transfers.cancel', $stockTransfer) }}"
              onsubmit="return confirm('Cancel this transfer?')">
            @csrf @method('PATCH')
            <button class="btn btn--danger">Cancel Transfer</button>
        </form>
        @endif
    </div>

    {{-- Items Table --}}
    @if($stockTransfer->status === 'approved')
    <form method="POST" action="{{ route('org.transfers.dispatch', $stockTransfer) }}">
        @csrf @method('PATCH')
    @endif
    @if($stockTransfer->status === 'dispatched')
    <form method="POST" action="{{ route('org.transfers.receive', $stockTransfer) }}">
        @csrf @method('PATCH')
    @endif

    <div class="table-card">
        <div class="card-body"><h3>Transfer Items</h3></div>
        <table class="table">
            <thead>
                <tr>
                    <th>Product (Source)</th>
                    <th>Product (Destination)</th>
                    <th>Requested</th>
                    @if(in_array($stockTransfer->status, ['approved'])) <th>Qty to Dispatch</th> @endif
                    @if(in_array($stockTransfer->status, ['dispatched','received'])) <th>Dispatched</th> @endif
                    @if($stockTransfer->status === 'dispatched') <th>Qty to Receive</th> @endif
                    @if($stockTransfer->status === 'received') <th>Received</th> @endif
                </tr>
            </thead>
            <tbody>
                @foreach($stockTransfer->items as $item)
                <tr>
                    <td data-label="Product (Source)">{{ $item->product_name }}</td>
                    <td data-label="Product (Destination)">
                        @if($item->destProduct)
                            {{ $item->destProduct->name }}
                        @else
                            <span class="text-danger">No destination mapping (created before this was required)</span>
                        @endif
                    </td>
                    <td data-label="Requested">{{ $item->quantity_requested }}</td>
                    @if($stockTransfer->status === 'approved')
                        <td data-label="Qty to Dispatch">
                            <input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $item->id }}">
                            <input type="number" name="items[{{ $loop->index }}][quantity_dispatched]"
                                   class="form-control" style="width:80px;"
                                   min="0" max="{{ $item->quantity_requested }}" value="{{ $item->quantity_requested }}">
                        </td>
                    @endif
                    @if(in_array($stockTransfer->status, ['dispatched','received'])) <td data-label="Dispatched">{{ $item->quantity_dispatched }}</td> @endif
                    @if($stockTransfer->status === 'dispatched')
                        <td data-label="Qty to Receive">
                            <input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $item->id }}">
                            <input type="number" name="items[{{ $loop->index }}][quantity_received]"
                                   class="form-control" style="width:80px;"
                                   min="0" max="{{ $item->quantity_dispatched }}" value="{{ $item->quantity_dispatched }}">
                        </td>
                    @endif
                    @if($stockTransfer->status === 'received') <td data-label="Received">{{ $item->quantity_received }}</td> @endif
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($stockTransfer->status === 'approved')
        <div style="padding:1rem;">
            <button type="submit" class="btn btn--primary">Confirm Dispatch</button>
        </div>
        @endif
        @if($stockTransfer->status === 'dispatched')
        <div style="padding:1rem;">
            <button type="submit" class="btn btn--success">Confirm Receipt</button>
        </div>
        @endif
    </div>

    @if(in_array($stockTransfer->status, ['approved','dispatched']))
    </form>
    @endif

    @if($stockTransfer->notes)
    <div class="table-card" style="margin-top:1.5rem;">
        <div class="card-body"><strong>Notes:</strong> {{ $stockTransfer->notes }}</div>
    </div>
    @endif
</div>
@endsection
