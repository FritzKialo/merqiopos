<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupplierController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    // â”€â”€ List all suppliers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function index(Request $request) {
        $businessId = $this->businessId();
        $query = Supplier::forBusiness($businessId)
                    ->withCount('purchaseOrders');

        // Search
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name',  'like', "%{$s}%")
                  ->orWhere('phone','like', "%{$s}%")
                  ->orWhere('email','like', "%{$s}%");
            });
        }

        // Filter by active status
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $suppliers = $query->orderBy('name')
                        ->paginate(15)
                        ->withQueryString();

        // Summary stats
        $stats = [
            'total'    => Supplier::forBusiness($businessId)->count(),
            'active'   => Supplier::forBusiness($businessId)->where('is_active', true)->count(),
            'inactive' => Supplier::forBusiness($businessId)->where('is_active', false)->count(),
        ];

        return view('suppliers.index', compact('suppliers', 'stats'));
    }

    // â”€â”€ Show create form â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function create() {
        return view('suppliers.create');
    }

    // â”€â”€ Store new supplier â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function store(Request $request) {
        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'email'          => 'nullable|email',
            'phone'          => 'nullable|string|max:50',
            'address'        => 'nullable|string',
            'contact_person' => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:100',
            'notes'          => 'nullable|string',
            'is_active'      => 'boolean',
        ]);

        $data['business_id'] = $this->businessId();
        $data['is_active']   = $request->boolean('is_active', true);

        Supplier::create($data);

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Supplier added successfully.');
    }

    // â”€â”€ Show supplier profile â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function show(Supplier $supplier) {
        $this->authorizeSupplier($supplier);

        $recentOrders = $supplier->purchaseOrders()
            ->with('items')
            ->latest('order_date')
            ->take(10)
            ->get();

        $allOrders = $supplier->purchaseOrders();

        $stats = [
            'total_orders'    => (clone $allOrders)->count(),
            // Cash actually paid out, not the total value ordered — otherwise
            // this sits right next to Unpaid Balance showing the exact same
            // figure, which reads as a contradiction to a business owner.
            'total_spent'     => SupplierPayment::where('supplier_id', $supplier->id)
                ->where('type', 'payment')
                ->sum('amount'),
            'unpaid_balance'  => (clone $allOrders)
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->selectRaw('SUM(total - amount_paid) as balance')
                ->value('balance') ?? 0,
        ];

        return view('suppliers.show', compact('supplier', 'recentOrders', 'stats'));
    }

    // â”€â”€ Show edit form â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function edit(Supplier $supplier) {
        $this->authorizeSupplier($supplier);
        return view('suppliers.edit', compact('supplier'));
    }

    // â”€â”€ Update supplier â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function update(Request $request, Supplier $supplier) {
        $this->authorizeSupplier($supplier);

        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'email'          => 'nullable|email',
            'phone'          => 'nullable|string|max:50',
            'address'        => 'nullable|string',
            'contact_person' => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:100',
            'notes'          => 'nullable|string',
            'is_active'      => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $supplier->update($data);

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('success', 'Supplier updated successfully.');
    }

    // â”€â”€ Delete supplier â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function destroy(Supplier $supplier) {
        $this->authorizeSupplier($supplier);

        $supplier->delete();

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Supplier deleted successfully.');
    }

    // â”€â”€ Authorization helper â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    private function authorizeSupplier(Supplier $supplier): void {
        if ($supplier->business_id !== $this->businessId()) {
            abort(403, 'Unauthorized action.');
        }
    }
}

