<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;

/**
 * The read-only, login-free "manager dashboard" — an owner generates a
 * link under Settings and hands it to whoever needs to glance at the
 * shop's numbers (a manager, or the owner themselves checking from their
 * phone) without a full login. Deliberately narrow: no create/update
 * routes exist here at all, so unlike the REST API's token, this one
 * cannot modify anything even if it ends up somewhere it shouldn't.
 */
class ManagerViewController extends Controller
{
    public function show(Request $request, string $token)
    {
        $business = Business::where('dashboard_token', hash('sha256', $token))->firstOrFail();

        $today = now()->startOfDay();

        $todaySales = Sale::forBusiness($business->id)
            ->where('sale_status', 'completed')
            ->where('created_at', '>=', $today);

        $todayTotal = (clone $todaySales)->sum('total_amount');
        $todayCount = (clone $todaySales)->count();

        $recentSales = Sale::forBusiness($business->id)
            ->where('sale_status', 'completed')
            ->with('customer', 'user')
            ->latest()
            ->limit(15)
            ->get();

        $products = Product::forBusiness($business->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $lowStock = $products->filter(fn ($p) => $p->reorder_level > 0 && $p->stock_qty <= $p->reorder_level);

        return view('manager-view', [
            'business'    => $business,
            'todayTotal'  => $todayTotal,
            'todayCount'  => $todayCount,
            'recentSales' => $recentSales,
            'products'    => $products,
            'lowStock'    => $lowStock,
        ]);
    }
}
