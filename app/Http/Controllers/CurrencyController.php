<?php

namespace App\Http\Controllers;

use App\Models\CurrencyRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CurrencyController extends Controller
{
    private function businessId(): int
    {
        return Auth::user()->currentBusiness()->id;
    }

    public function index()
    {
        $rates            = CurrencyRate::where('business_id', $this->businessId())->get();
        $commonCurrencies = CurrencyRate::$commonCurrencies;
        return view('settings.currencies', compact('rates', 'commonCurrencies'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'currency_code' => ['required', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'rate'          => 'required|numeric|min:0.0001',
        ]);

        CurrencyRate::updateOrCreate(
            ['business_id' => $this->businessId(), 'currency_code' => $request->currency_code],
            ['rate' => $request->rate]
        );

        return back()->with('success', 'Currency rate saved.');
    }

    public function update(Request $request, CurrencyRate $rate)
    {
        abort_if($rate->business_id !== $this->businessId(), 403);

        $request->validate([
            'rate' => 'required|numeric|min:0.0001',
        ]);

        $rate->update(['rate' => $request->rate]);
        return back()->with('success', 'Rate updated.');
    }

    public function destroy(CurrencyRate $rate)
    {
        abort_if($rate->business_id !== $this->businessId(), 403);
        $rate->delete();
        return back()->with('success', 'Currency removed.');
    }
}
