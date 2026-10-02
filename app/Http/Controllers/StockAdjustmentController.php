<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockAdjustmentController extends Controller {

    // â”€â”€ Helper â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    // â”€â”€ Authorization guard â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    private function authorizeProduct(Product $product): void {
        if ($product->business_id !== $this->businessId()) {
            abort(403, 'Unauthorized action.');
        }
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    //  INDEX â€” list all adjustments
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

    public function index(Request $request) {
        $businessId = $this->businessId();

        $query = StockAdjustment::with(['product', 'user'])
            ->forBusiness($businessId)
            ->latest();

        // Filter by product
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $adjustments = $query->paginate(20)->withQueryString();

        // Active products for the filter dropdown
        $products = Product::forBusiness($businessId)
            ->active()
            ->orderBy('name')
            ->get();

        // Stats
        $today     = now()->toDateString();
        $monthStart = now()->startOfMonth();

        $stats = [
            'additions_today'   => StockAdjustment::forBusiness($businessId)
                ->where('type', 'addition')
                ->whereDate('created_at', $today)
                ->sum('quantity_change'),

            'deductions_today'  => StockAdjustment::forBusiness($businessId)
                ->where('type', 'deduction')
                ->whereDate('created_at', $today)
                ->sum('quantity_change'),

            'adjusted_this_month' => StockAdjustment::forBusiness($businessId)
                ->where('created_at', '>=', $monthStart)
                ->distinct('product_id')
                ->count('product_id'),
        ];

        return view('inventory.adjustments', compact(
            'adjustments', 'products', 'stats'
        ));
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    //  CREATE â€” show form
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

    public function create(Request $request) {
        $businessId = $this->businessId();

        $products = Product::forBusiness($businessId)
            ->active()
            ->orderBy('name')
            ->get();

        $selectedProductId = $request->query('product_id');

        return view('inventory.adjustment-create', compact(
            'products', 'selectedProductId'
        ));
    }

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    //  STORE â€” process form
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

    public function store(Request $request) {
        $businessId = $this->businessId();

        // Base validation
        $request->validate([
            'product_id'      => ['required', 'integer', 'exists:products,id'],
            'type'            => ['required', 'in:addition,deduction,correction'],
            'quantity_change' => ['required', 'integer', 'min:0'],
            'reason'          => ['required', 'string', 'max:255'],
            'notes'           => ['nullable', 'string', 'max:1000'],
            'reference'       => ['nullable', 'string', 'max:100'],
        ]);

        // Additional quantity constraint: addition/deduction require >= 1
        if (in_array($request->type, ['addition', 'deduction'])) {
            $request->validate([
                'quantity_change' => ['required', 'integer', 'min:1'],
            ]);
        }

        $product = Product::findOrFail($request->product_id);
        $this->authorizeProduct($product);

        // A deduction bigger than what's on hand used to be silently clamped
        // to zero while the history still recorded the full requested amount,
        // so the audit trail showed stock removals that never happened.
        if ($request->type === 'deduction' && (int) $request->quantity_change > (int) $product->stock_qty) {
            return back()->withInput()->with('error',
                "Cannot deduct {$request->quantity_change} — only {$product->stock_qty} in stock for {$product->name}.");
        }

        DB::transaction(function () use ($request, $product, $businessId) {
            $quantityBefore = $product->stock_qty;
            $quantityChange = (int) $request->quantity_change;

            switch ($request->type) {
                case 'addition':
                    $quantityAfter = $quantityBefore + $quantityChange;
                    break;

                case 'deduction':
                    $quantityAfter = max(0, $quantityBefore - $quantityChange);
                    break;

                case 'correction':
                default:
                    // quantity_change is treated as the absolute new value
                    $quantityAfter  = $quantityChange;
                    $quantityChange = abs($quantityAfter - $quantityBefore);
                    break;
            }

            // Update product stock
            $product->update(['stock_qty' => $quantityAfter]);

            // Record adjustment
            StockAdjustment::create([
                'business_id'     => $businessId,
                'product_id'      => $product->id,
                'user_id'         => Auth::id(),
                'type'            => $request->type,
                'quantity_before' => $quantityBefore,
                'quantity_change' => $request->type === 'correction'
                                        ? $quantityChange
                                        : (int) $request->quantity_change,
                'quantity_after'  => $quantityAfter,
                'reason'          => $request->reason,
                'notes'           => $request->notes,
                'reference'       => $request->reference,
            ]);
        });

        \App\Models\AuditLog::record('stock.adjusted', $product, [
            'type' => $request->type, 'quantity' => (int) $request->quantity_change,
            'reason' => $request->reason, 'stock_now' => (int) $product->fresh()->stock_qty,
        ]);

        WebhookService::dispatch('stock.adjusted', $businessId, [
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'type'         => $request->type,
            'reason'       => $request->reason,
        ]);

        return redirect()
            ->route('inventory.adjustments')
            ->with('success', 'Stock adjustment recorded successfully.');
    }
}

