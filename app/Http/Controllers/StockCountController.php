<?php
namespace App\Http\Controllers;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockCountController extends Controller {
    private function businessId() { return Auth::user()->currentBusiness()->id; }

    public function index() {
        $counts = StockCount::where('business_id', $this->businessId())->with('user')->latest()->paginate(20);
        return view('inventory.stock-counts.index', compact('counts'));
    }

    public function create() {
        return view('inventory.stock-counts.create');
    }

    public function store(Request $request) {
        $businessId = $this->businessId();
        $n = StockCount::where('business_id', $businessId)->count();
        $reference = 'SC-' . date('Ymd') . '-' . str_pad($n + 1, 3, '0', STR_PAD_LEFT);

        $count = StockCount::create([
            'business_id' => $businessId,
            'reference' => $reference,
            'status' => 'in_progress',
            'counted_by' => Auth::id(),
            'notes' => $request->notes,
            'started_at' => now(),
        ]);

        // Snapshot all active products
        Product::where('business_id', $businessId)->where('status', 'active')->each(function($product) use ($count) {
            StockCountItem::create([
                'stock_count_id' => $count->id,
                'product_id' => $product->id,
                'system_qty' => $product->stock_qty ?? 0,
                'counted_qty' => null,
            ]);
        });

        return redirect()->route('inventory.stock-counts.show', $count)->with('success', 'Stock count started. Enter counted quantities.');
    }

    public function show(StockCount $count) {
        abort_if($count->business_id !== $this->businessId(), 403);
        $count->load('items.product');
        return view('inventory.stock-counts.show', compact('count'));
    }

    public function update(Request $request, StockCount $count) {
        abort_if($count->business_id !== $this->businessId(), 403);
        if (!$count->isEditable()) return back()->with('error', 'Cannot edit completed count.');
        foreach ($request->items ?? [] as $itemId => $qty) {
            StockCountItem::where('id', $itemId)->where('stock_count_id', $count->id)->update(['counted_qty' => $qty]);
        }
        return back()->with('success', 'Quantities saved.');
    }

    public function complete(StockCount $count) {
        // The most consequential one in this controller — this actually
        // writes stock adjustments and changes real inventory quantities.
        // My automated scan for this whole sweep initially missed this
        // specific method: it references 'business_id' in the body (copying
        // it into the stock_adjustments insert), which is a false positive
        // for "has an authorization check" — worth remembering that pattern
        // can hide a real gap, not just prove one's absent.
        abort_if($count->business_id !== $this->businessId(), 403);
        $count->load('items.product');
        $unfilled = $count->items->whereNull('counted_qty')->count();
        if ($unfilled > 0) return back()->with('error', "$unfilled items still need counted quantities.");

        DB::transaction(function() use ($count) {
            foreach ($count->items as $item) {
                $variance = $item->counted_qty - $item->system_qty;
                if ($variance != 0) {
                    // Adjust stock
                    $item->product->increment('stock_qty', $variance);
                    // Record adjustment — was raw-inserting columns
                    // ('adjustment_type', 'quantity') that don't exist on
                    // stock_adjustments and omitting the NOT-NULL 'type',
                    // 'quantity_before', 'quantity_after' columns, so this
                    // threw a SQL error (rolled back by the transaction) on
                    // every stock count with any variance. Matches the
                    // pattern StockAdjustmentController::store() already uses.
                    StockAdjustment::create([
                        'business_id'     => $count->business_id,
                        'product_id'      => $item->product_id,
                        'user_id'         => Auth::id(),
                        'type'            => 'correction',
                        'quantity_before' => $item->system_qty,
                        'quantity_change' => abs($variance),
                        'quantity_after'  => $item->counted_qty,
                        'reason'          => 'Stock count',
                        'notes'           => 'Stock count ' . $count->reference,
                        'reference'       => $count->reference,
                    ]);
                }
            }
            $count->update(['status' => 'completed', 'completed_at' => now()]);
        });

        return redirect()->route('inventory.stock-counts.index')->with('success', 'Stock count completed and adjustments posted.');
    }

    public function cancel(StockCount $count) {
        abort_if($count->business_id !== $this->businessId(), 403);
        $count->update(['status' => 'cancelled']);
        return back()->with('success', 'Stock count cancelled.');
    }

    public function destroy(StockCount $count) {
        abort_if($count->business_id !== $this->businessId(), 403);
        if ($count->status !== 'draft') return back()->with('error', 'Can only delete draft counts.');
        $count->delete();
        return redirect()->route('inventory.stock-counts.index')->with('success', 'Deleted.');
    }
}
