<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeliveryNoteController extends Controller
{
    private function business()
    {
        return Auth::user()->currentBusiness();
    }

    private function businessId(): int
    {
        return $this->business()->id;
    }

    private function authorize(DeliveryNote $dn): void
    {
        abort_if($dn->business_id !== $this->businessId(), 403);
    }

    public function index()
    {
        $notes = DeliveryNote::forBusiness($this->businessId())
            ->with(['customer', 'user'])
            ->latest()
            ->paginate(20);

        return view('delivery-notes.index', compact('notes'));
    }

    public function create(Request $request)
    {
        $businessId = $this->businessId();
        $customers  = Customer::where('business_id', $businessId)->orderBy('name')->get();
        $products   = Product::forBusiness($businessId)->active()->orderBy('name')->get();

        $invoice = null;
        $sale    = null;

        if ($request->filled('invoice_id')) {
            $invoice = Invoice::where('id', $request->invoice_id)
                ->where('business_id', $businessId)
                ->with(['customer', 'items'])
                ->first();
        }

        if ($request->filled('sale_id')) {
            $sale = Sale::where('id', $request->sale_id)
                ->where('business_id', $businessId)
                ->with(['customer', 'items.product'])
                ->first();
        }

        return view('delivery-notes.create', compact('customers', 'products', 'invoice', 'sale'));
    }

    public function store(Request $request)
    {
        $businessId = $this->businessId();

        // All four were unscoped exists: checks — could link this note to
        // another business's customer/invoice/sale/product.
        $request->validate([
            'customer_id'       => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('customers', 'id')->where('business_id', $businessId)],
            'invoice_id'        => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('invoices', 'id')->where('business_id', $businessId)],
            'sale_id'           => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('sales', 'id')->where('business_id', $businessId)],
            'delivery_address'  => 'nullable|string|max:1000',
            'notes'             => 'nullable|string|max:2000',
            'items'             => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit'        => 'nullable|string|max:30',
            'items.*.product_id'  => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('products', 'id')->where('business_id', $businessId)],
        ]);

        DB::transaction(function () use ($request, $businessId) {
            $dn = DeliveryNote::create([
                'business_id'     => $businessId,
                'user_id'         => Auth::id(),
                'customer_id'     => $request->customer_id,
                'invoice_id'      => $request->invoice_id,
                'sale_id'         => $request->sale_id,
                'number'          => DeliveryNote::nextNumber($businessId),
                'status'          => 'draft',
                'delivery_address'=> $request->delivery_address,
                'notes'           => $request->notes,
            ]);

            foreach ($request->items as $item) {
                DeliveryNoteItem::create([
                    'delivery_note_id' => $dn->id,
                    'product_id'       => $item['product_id'] ?? null,
                    'description'      => $item['description'],
                    'quantity'         => $item['quantity'],
                    'unit'             => $item['unit'] ?? null,
                ]);
            }

            $this->createdId = $dn->id;
        });

        return redirect()->route('delivery-notes.show', $this->createdId)
            ->with('success', 'Delivery note created.');
    }

    public function show(DeliveryNote $deliveryNote)
    {
        $this->authorize($deliveryNote);
        $deliveryNote->load(['customer', 'items.product', 'invoice', 'sale', 'user', 'business']);

        return view('delivery-notes.show', compact('deliveryNote'));
    }

    public function dispatch(DeliveryNote $deliveryNote)
    {
        $this->authorize($deliveryNote);

        if ($deliveryNote->status !== 'draft') {
            return back()->with('error', 'Only draft delivery notes can be dispatched.');
        }

        $deliveryNote->update([
            'status'        => 'dispatched',
            'dispatched_at' => now(),
        ]);

        return back()->with('success', 'Delivery note marked as dispatched.');
    }

    public function deliver(DeliveryNote $deliveryNote)
    {
        $this->authorize($deliveryNote);

        if ($deliveryNote->status !== 'dispatched') {
            return back()->with('error', 'Only dispatched delivery notes can be marked as delivered.');
        }

        $deliveryNote->update([
            'status'       => 'delivered',
            'delivered_at' => now(),
        ]);

        return back()->with('success', 'Delivery note marked as delivered.');
    }

    public function pdf(DeliveryNote $deliveryNote)
    {
        $this->authorize($deliveryNote);
        $deliveryNote->load(['customer', 'items.product', 'invoice', 'business', 'user']);
        $business = $this->business();

        return view('delivery-notes.pdf', compact('deliveryNote', 'business'));
    }
}
