{{-- KRA eTIMS reversal status for a cancelled sale, return or credit note.
     Usage: @include('partials.etims-refund-status', ['etimsType' => 'sale_cancel'|'sale_return'|'credit_note', 'etimsId' => $id]) --}}
@php $etimsRefund = \App\Models\EtimsRefund::forSource($etimsType, (int) $etimsId); @endphp
@if($etimsRefund)
<div class="table-card" style="margin-top:1.5rem;">
    <div class="card-body">
        <h3 style="margin-bottom:1rem;">KRA eTIMS Refund</h3>
        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
            @if($etimsRefund->status === 'submitted')
                <span class="badge badge-success">Reported</span>
                @if($etimsRefund->cuin)
                    <span style="font-size:0.85rem;color:var(--color-text-muted);">Receipt: <strong style="color:var(--color-text);">{{ $etimsRefund->cuin }}</strong></span>
                @endif
                @if($etimsRefund->submitted_at)
                    <span style="font-size:0.85rem;color:var(--color-text-muted);">{{ $etimsRefund->submitted_at->format('d M Y H:i') }}</span>
                @endif
            @elseif($etimsRefund->status === 'pending')
                <span class="badge badge-secondary">Pending</span>
                <span style="font-size:0.85rem;color:var(--color-text-muted);">The refund is queued and will be reported to KRA shortly.</span>
            @elseif($etimsRefund->status === 'skipped')
                <span class="badge badge-secondary">Not reported</span>
                <span style="font-size:0.85rem;color:var(--color-text-muted);">{{ $etimsRefund->message }}</span>
            @else
                <span class="badge badge-danger">Failed</span>
                <span style="font-size:0.85rem;color:var(--color-danger);">{{ $etimsRefund->message }}</span>
                @if(auth()->user()->hasAnyRole('owner','manager'))
                <form method="POST" action="{{ route('etims.resubmit') }}" style="display:inline;">
                    @csrf
                    <input type="hidden" name="type" value="refund">
                    <input type="hidden" name="id" value="{{ $etimsRefund->id }}">
                    <button type="submit" class="btn btn-warning">Resubmit to eTIMS</button>
                </form>
                @endif
            @endif
        </div>
    </div>
</div>
@endif
