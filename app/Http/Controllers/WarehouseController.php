<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WarehouseController extends Controller
{
    private function businessId()
    {
        return Auth::user()->currentBusiness()->id;
    }

    public function index()
    {
        $warehouses = Warehouse::where('business_id', $this->businessId())->with('stock')->get();
        return view('warehouses.index', compact('warehouses'));
    }

    public function create()
    {
        return view('warehouses.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'code'     => 'nullable|string|max:20',
            'location' => 'nullable|string|max:200',
        ]);

        $isFirst = Warehouse::where('business_id', $this->businessId())->count() === 0;

        Warehouse::create(
            $request->only(['name', 'code', 'location']) + [
                'business_id' => $this->businessId(),
                'is_default'  => $isFirst,
            ]
        );

        return redirect()->route('warehouses.index')->with('success', 'Warehouse created.');
    }

    public function show(Warehouse $warehouse)
    {
        if ($warehouse->business_id !== $this->businessId()) abort(403);
        $stock    = $warehouse->stock()->with('product')->orderBy('bin_location')->get();
        $products = Product::where('business_id', $this->businessId())->where('status', 'active')->orderBy('name')->get();
        return view('warehouses.show', compact('warehouse', 'stock', 'products'));
    }

    public function updateStock(Request $request, Warehouse $warehouse)
    {
        if ($warehouse->business_id !== $this->businessId()) abort(403);

        // Unscoped exists: previously — could link this warehouse's stock
        // record to another business's product.
        $request->validate([
            'product_id'   => ['required', \Illuminate\Validation\Rule::exists('products', 'id')->where('business_id', $this->businessId())],
            'quantity'     => 'required|numeric|min:0',
            'bin_location' => 'nullable|string|max:50',
        ]);

        WarehouseStock::updateOrCreate(
            ['warehouse_id' => $warehouse->id, 'product_id' => $request->product_id],
            [
                'business_id'  => $this->businessId(),
                'quantity'     => $request->quantity,
                'bin_location' => $request->bin_location,
            ]
        );

        return back()->with('success', 'Stock updated.');
    }

    public function destroy(Warehouse $warehouse)
    {
        if ($warehouse->business_id !== $this->businessId()) abort(403);
        $warehouse->delete();
        return redirect()->route('warehouses.index')->with('success', 'Warehouse deleted.');
    }

    public function bins(Warehouse $warehouse)
    {
        if ($warehouse->business_id !== $this->businessId()) abort(403);
        $bins = $warehouse->stock()
            ->with('product')
            ->whereNotNull('bin_location')
            ->orderBy('bin_location')
            ->get()
            ->groupBy('bin_location');
        return view('warehouses.bins', compact('warehouse', 'bins'));
    }

    public function moveBin(Request $request, Warehouse $warehouse)
    {
        if ($warehouse->business_id !== $this->businessId()) abort(403);
        $request->validate([
            'stock_id'         => 'required|exists:warehouse_stock,id',
            'new_bin_location' => 'required|string|max:50',
        ]);
        WarehouseStock::where('id', $request->stock_id)
            ->where('warehouse_id', $warehouse->id)
            ->update(['bin_location' => $request->new_bin_location]);
        return back()->with('success', 'Item moved to bin ' . $request->new_bin_location . '.');
    }

    public function clearBin(Request $request, Warehouse $warehouse)
    {
        if ($warehouse->business_id !== $this->businessId()) abort(403);
        $request->validate(['bin_location' => 'required|string|max:50']);
        WarehouseStock::where('warehouse_id', $warehouse->id)
            ->where('bin_location', $request->bin_location)
            ->update(['bin_location' => null]);
        return back()->with('success', 'Bin ' . $request->bin_location . ' cleared.');
    }
}
