<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\VoidRequest;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VoidRequestController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    // ── Manager queue: pending void requests awaiting approval ─────────────
    public function index() {
        $businessId = $this->businessId();

        $pending = VoidRequest::with(['sale', 'voidReason', 'requestedBy'])
            ->forBusiness($businessId)
            ->pending()
            ->latest()
            ->get();

        $recent = VoidRequest::with(['sale', 'voidReason', 'requestedBy', 'reviewedBy'])
            ->forBusiness($businessId)
            ->where('status', '!=', 'pending')
            ->latest('reviewed_at')
            ->limit(20)
            ->get();

        return view('void-requests.index', compact('pending', 'recent'));
    }

    // ── Cashier: propose a void — the sale is NOT touched until a manager
    // approves it. This is the only cancellation path available to a
    // cashier; owner/manager keep the direct "Cancel Sale" action on the
    // sale page, which auto-approves itself (see SalesController::cancel()).
    public function store(Request $request, Sale $sale) {
        abort_if($sale->business_id !== $this->businessId(), 403);

        if ($sale->sale_status === 'cancelled') {
            return back()->with('error', 'This sale is already cancelled.');
        }
        if ($sale->voidRequests()->pending()->exists()) {
            return back()->with('error', 'A void request for this sale is already pending manager approval.');
        }

        $request->validate([
            'void_reason_id' => 'required|integer|exists:void_reasons,id',
            'note'           => 'nullable|string|max:500',
        ]);

        $voidRequest = VoidRequest::create([
            'business_id'    => $this->businessId(),
            'sale_id'        => $sale->id,
            'void_reason_id' => $request->void_reason_id,
            'note'           => $request->note,
            'status'         => 'pending',
            'requested_by'   => Auth::id(),
        ]);

        \App\Models\AppNotification::notifyApprovers(
            Auth::user()->currentBusiness(), Auth::id(), 'void_requested', 'Void Approval Needed',
            Auth::user()->name . " asked to void sale {$sale->invoice_number} (KSh " . number_format($sale->total_amount, 2) . ').',
            route('void-requests.index'), 'warning'
        );

        return back()->with('success', "Void requested for sale {$sale->invoice_number} — awaiting manager approval.");
    }

    // ── Manager: approve — actually cancels the sale ────────────────────────
    public function approve(VoidRequest $voidRequest, SaleService $saleService) {
        abort_if($voidRequest->business_id !== $this->businessId(), 403);

        if (!$voidRequest->isPending()) {
            return back()->with('error', 'This void request has already been reviewed.');
        }

        DB::beginTransaction();
        try {
            $sale = $voidRequest->sale()->with('items')->firstOrFail();
            $saleService->cancelSale($sale);

            $voidRequest->update([
                'status'      => 'approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            DB::commit();

            \App\Models\AppNotification::notifyUser($voidRequest->business_id, $voidRequest->requested_by, 'void_approved', 'Void Approved',
                "Your void request for sale {$sale->invoice_number} was approved.", null, 'check-circle');

            return back()->with('success', "Void approved — sale {$sale->invoice_number} has been cancelled.");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    // ── Manager: reject — sale stays active, cashier sees it was declined ──
    public function reject(VoidRequest $voidRequest) {
        abort_if($voidRequest->business_id !== $this->businessId(), 403);

        if (!$voidRequest->isPending()) {
            return back()->with('error', 'This void request has already been reviewed.');
        }

        $voidRequest->update([
            'status'      => 'rejected',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        \App\Models\AppNotification::notifyUser($voidRequest->business_id, $voidRequest->requested_by, 'void_rejected', 'Void Rejected',
            'Your void request was rejected — the sale stays active.', null, 'x-circle');

        return back()->with('success', 'Void request rejected — the sale remains active.');
    }
}
