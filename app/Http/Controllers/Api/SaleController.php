<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    private function business(Request $request)
    {
        return $request->get('_api_business');
    }

    public function index(Request $request)
    {
        $business = $this->business($request);
        $query = Sale::with(['customer', 'items'])->forBusiness($business->id);

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->filled('sale_status')) {
            $query->where('sale_status', $request->sale_status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $sales = $query->orderByDesc('created_at')->paginate(50);

        return response()->json([
            'data' => $sales->items(),
            'meta' => [
                'current_page' => $sales->currentPage(),
                'last_page'    => $sales->lastPage(),
                'per_page'     => $sales->perPage(),
                'total'        => $sales->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id)
    {
        $sale = Sale::with(['customer', 'items.product'])
            ->forBusiness($this->business($request)->id)
            ->findOrFail($id);

        return response()->json(['data' => $sale]);
    }

    public function summary(Request $request)
    {
        $business = $this->business($request);
        $month = $request->get('month', now()->month);
        $year  = $request->get('year',  now()->year);

        $sales = Sale::forBusiness($business->id)
            ->where('sale_status', 'completed')
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year);

        return response()->json([
            'data' => [
                'month'        => $month,
                'year'         => $year,
                'total_sales'  => (clone $sales)->sum('total_amount'),
                'total_paid'   => (clone $sales)->sum('paid_amount'),
                'total_unpaid' => (clone $sales)->sum('balance_due'),
                'count'        => (clone $sales)->count(),
            ]
        ]);
    }
}
