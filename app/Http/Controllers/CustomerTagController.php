<?php

namespace App\Http\Controllers;

use App\Models\CustomerTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerTagController extends Controller
{
    private function businessId(): int
    {
        return Auth::user()->currentBusiness()->id;
    }

    public function index()
    {
        $tags = CustomerTag::forBusiness($this->businessId())
            ->withCount('customers')
            ->orderBy('name')
            ->get();
        return view('settings.customer-tags', compact('tags'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:100',
            'color' => 'nullable|string|max:7',
        ]);
        CustomerTag::create([
            'business_id' => $this->businessId(),
            'name'        => $request->name,
            'color'       => $request->color ?? '#6b7280',
        ]);
        return back()->with('success', 'Tag created.');
    }

    public function update(Request $request, CustomerTag $customerTag)
    {
        abort_if($customerTag->business_id !== $this->businessId(), 403);
        $request->validate(['name' => 'required|string|max:100', 'color' => 'nullable|string|max:7']);
        $customerTag->update(['name' => $request->name, 'color' => $request->color ?? $customerTag->color]);
        return back()->with('success', 'Tag updated.');
    }

    public function destroy(CustomerTag $customerTag)
    {
        abort_if($customerTag->business_id !== $this->businessId(), 403);
        $customerTag->delete();
        return back()->with('success', 'Tag deleted.');
    }
}
