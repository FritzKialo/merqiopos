<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PurchaseRequisitionController extends Controller
{
    private function businessId(): int
    {
        return Auth::user()->currentBusiness()->id;
    }

    public function index(Request $request)
    {
        $query = PurchaseRequisition::forBusiness($this->businessId())
            ->with(['requestedBy', 'approvedBy'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requisitions = $query->paginate(20)->withQueryString();
        $statuses     = ['pending', 'approved', 'rejected', 'converted'];

        return view('purchase-requisitions.index', compact('requisitions', 'statuses'));
    }

    public function create()
    {
        $products = Product::forBusiness($this->businessId())->active()->orderBy('name')->get();
        return view('purchase-requisitions.create', compact('products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'urgency'       => 'required|in:low,normal,urgent',
            'required_by'   => 'nullable|date',
            'justification' => 'nullable|string|max:2000',
            'items'         => 'required|array|min:1',
            'items.*.description' => 'required|string|max:300',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit'        => 'nullable|string|max:50',
            'items.*.estimated_unit_price' => 'nullable|numeric|min:0',
            'items.*.product_id'  => 'nullable|exists:products,id',
        ]);

        $businessId = $this->businessId();

        $pr = PurchaseRequisition::create([
            'business_id'    => $businessId,
            'requested_by'   => Auth::id(),
            'reference'      => PurchaseRequisition::generateReference($businessId),
            'status'         => 'pending',
            'urgency'        => $request->urgency,
            'justification'  => $request->justification,
            'required_by'    => $request->required_by,
        ]);

        foreach ($request->items as $item) {
            $pr->items()->create([
                'description'          => $item['description'],
                'quantity'             => $item['quantity'],
                'unit'                 => $item['unit'] ?? null,
                'estimated_unit_price' => $item['estimated_unit_price'] ?? null,
                'product_id'           => $item['product_id'] ?? null,
            ]);
        }

        return redirect()->route('purchase-requisitions.show', $pr)->with('success', 'Purchase requisition submitted.');
    }

    public function show(PurchaseRequisition $purchaseRequisition)
    {
        $this->authorize($purchaseRequisition);
        $purchaseRequisition->load(['items.product', 'requestedBy', 'approvedBy']);
        return view('purchase-requisitions.show', compact('purchaseRequisition'));
    }

    public function edit(PurchaseRequisition $purchaseRequisition)
    {
        $this->authorize($purchaseRequisition);
        $products = Product::forBusiness($this->businessId())->active()->orderBy('name')->get();
        $purchaseRequisition->load('items');
        return view('purchase-requisitions.edit', compact('purchaseRequisition', 'products'));
    }

    public function update(Request $request, PurchaseRequisition $purchaseRequisition)
    {
        $this->authorize($purchaseRequisition);
        if ($purchaseRequisition->status !== 'pending') {
            return back()->with('error', 'Only pending requisitions can be edited.');
        }

        $request->validate([
            'urgency'       => 'required|in:low,normal,urgent',
            'required_by'   => 'nullable|date',
            'justification' => 'nullable|string|max:2000',
            'items'         => 'required|array|min:1',
            'items.*.description' => 'required|string|max:300',
            'items.*.quantity'    => 'required|numeric|min:0.01',
        ]);

        $purchaseRequisition->update($request->only(['urgency', 'required_by', 'justification']));
        $purchaseRequisition->items()->delete();

        foreach ($request->items as $item) {
            $purchaseRequisition->items()->create([
                'description'          => $item['description'],
                'quantity'             => $item['quantity'],
                'unit'                 => $item['unit'] ?? null,
                'estimated_unit_price' => $item['estimated_unit_price'] ?? null,
                'product_id'           => $item['product_id'] ?? null,
            ]);
        }

        return redirect()->route('purchase-requisitions.show', $purchaseRequisition)->with('success', 'Requisition updated.');
    }

    public function destroy(PurchaseRequisition $purchaseRequisition)
    {
        $this->authorize($purchaseRequisition);
        $purchaseRequisition->delete();
        return redirect()->route('purchase-requisitions.index')->with('success', 'Requisition deleted.');
    }

    public function approve(PurchaseRequisition $req)
    {
        $this->authorize($req);
        $this->authorizeManager();
        $req->update(['status' => 'approved', 'approved_by' => Auth::id()]);
        return back()->with('success', 'Requisition approved.');
    }

    public function reject(Request $request, PurchaseRequisition $req)
    {
        $this->authorize($req);
        $this->authorizeManager();
        $req->update(['status' => 'rejected']);
        return back()->with('success', 'Requisition rejected.');
    }

    public function convert(PurchaseRequisition $req)
    {
        $this->authorize($req);
        $this->authorizeManager();
        // Was marking the requisition 'converted' right here and flashing an
        // unused 'req_id' that nothing downstream ever read — the requisition
        // got flagged as converted while its line items were silently
        // dropped and no PO was ever actually created. Now we only pass the
        // requisition id through to the create form (PurchaseOrderController
        // ::create() loads and pre-fills it), and the requisition is only
        // marked 'converted' once a real PO is created from it (in
        // PurchaseOrderController::store()).
        return redirect()->route('purchases.create', ['requisition' => $req->id])
            ->with('info', 'Create a purchase order from requisition ' . $req->reference);
    }

    private function authorize(PurchaseRequisition $pr): void
    {
        if ($pr->business_id !== $this->businessId()) abort(403);
    }

    // approve/reject/convert previously only checked business scoping —
    // any staff member could approve/reject/convert ANY requisition in the
    // business, including their own, with zero manager gate. Same bug class
    // as ExpenseClaimController's approve/reject/pay, fixed the same way.
    private function authorizeManager(): void
    {
        abort_unless(Auth::user()->hasAnyRole('owner', 'manager', 'overall_manager'), 403);
    }
}
