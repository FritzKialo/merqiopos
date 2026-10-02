<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    private function business(Request $request)
    {
        return $request->get('_api_business');
    }

    public function index(Request $request)
    {
        $business = $this->business($request);
        $query = Customer::forBusiness($business->id);

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($qb) use ($q) {
                $qb->where('name',  'like', "%{$q}%")
                   ->orWhere('phone', 'like', "%{$q}%")
                   ->orWhere('email', 'like', "%{$q}%");
            });
        }
        if ($request->boolean('with_debt')) {
            $query->withDebt();
        }

        $customers = $query->orderBy('name')->paginate(50);

        return response()->json([
            'data' => $customers->items(),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page'    => $customers->lastPage(),
                'per_page'     => $customers->perPage(),
                'total'        => $customers->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id)
    {
        $customer = Customer::forBusiness($this->business($request)->id)->findOrFail($id);

        return response()->json(['data' => $customer]);
    }

    public function store(Request $request)
    {
        $business = $this->business($request);

        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'phone'   => 'nullable|string|max:20',
            'email'   => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'notes'   => 'nullable|string',
        ]);

        $validated['business_id'] = $business->id;
        $customer = Customer::create($validated);

        return response()->json(['data' => $customer, 'message' => 'Customer created.'], 201);
    }

    public function update(Request $request, int $id)
    {
        $customer = Customer::forBusiness($this->business($request)->id)->findOrFail($id);

        $validated = $request->validate([
            'name'    => 'sometimes|string|max:255',
            'phone'   => 'sometimes|nullable|string|max:20',
            'email'   => 'sometimes|nullable|email|max:255',
            'address' => 'sometimes|nullable|string|max:500',
            'notes'   => 'sometimes|nullable|string',
        ]);

        $customer->update($validated);

        return response()->json(['data' => $customer, 'message' => 'Customer updated.']);
    }
}
