<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\TableOrderRequest;
use Illuminate\Http\Request;

/**
 * The customer-facing side of QR self-ordering: a diner scans the code on their
 * table and lands here with no login. They can browse the menu and send a request,
 * but never write directly into the live order — a staff member has to approve it
 * first (TableController::approveRequest), same moderate-then-apply shape as the
 * online shop and the product waitlist.
 */
class TableOrderPublicController extends Controller
{
    private function findTable(string $token): RestaurantTable
    {
        return RestaurantTable::where('qr_token', $token)->firstOrFail();
    }

    public function show(string $token)
    {
        $table = $this->findTable($token);
        $business = $table->business;

        $products = Product::where('business_id', $business->id)
            ->where('status', 'active')->where('hide_in_shop', false)
            ->orderBy('category_id')->orderBy('name')->get();

        $myRequests = TableOrderRequest::where('restaurant_table_id', $table->id)
            ->latest('id')->limit(20)->get();

        return view('tables.public-order', compact('table', 'business', 'products', 'myRequests'));
    }

    public function store(string $token, Request $request)
    {
        $table = $this->findTable($token);

        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity'   => 'required|numeric|min:0.01|max:50',
            'notes'      => 'nullable|string|max:255',
        ]);

        $product = Product::where('business_id', $table->business_id)
            ->where('status', 'active')->where('hide_in_shop', false)
            ->find($data['product_id']);
        abort_unless($product, 422, 'That item is not available.');

        TableOrderRequest::create([
            'business_id'          => $table->business_id,
            'restaurant_table_id'  => $table->id,
            'product_id'           => $product->id,
            'product_name'         => $product->name,
            'quantity'             => $data['quantity'],
            'notes'                => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Sent! A member of staff will confirm it shortly.');
    }
}
