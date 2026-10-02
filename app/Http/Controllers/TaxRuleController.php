<?php

namespace App\Http\Controllers;

use App\Models\TaxRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaxRuleController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    public function index() {
        $taxRules = TaxRule::forBusiness($this->businessId())
            ->orderBy('priority')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('settings.tax-rules', compact('taxRules'));
    }

    public function store(Request $request) {
        $data = $this->validated($request);
        $data['business_id'] = $this->businessId();

        TaxRule::create($data);

        return back()->with('success', "Tax rule \"{$data['name']}\" added.");
    }

    public function update(Request $request, TaxRule $taxRule) {
        abort_if($taxRule->business_id !== $this->businessId(), 403);

        $taxRule->update($this->validated($request));

        return back()->with('success', "\"{$taxRule->name}\" updated.");
    }

    public function destroy(TaxRule $taxRule) {
        abort_if($taxRule->business_id !== $this->businessId(), 403);

        $name = $taxRule->name;
        $taxRule->delete();

        return back()->with('success', "Tax rule \"{$name}\" removed.");
    }

    private function validated(Request $request): array {
        $request->validate([
            'name'         => 'required|string|max:100',
            'code'         => 'required|string|max:20',
            'rate'         => 'required|numeric|min:0|max:100',
            'priority'     => 'nullable|integer|min:1|max:255',
            'tax_category' => 'required|in:standard,reduced,zero_rated,exempt',
            'inclusive'    => 'nullable|boolean',
            'enabled'      => 'nullable|boolean',
            'sort_order'   => 'nullable|integer|min:0',
        ]);

        return [
            'name'         => $request->name,
            'code'         => strtoupper($request->code),
            'rate'         => $request->rate,
            'priority'     => $request->priority ?? 1,
            'tax_category' => $request->tax_category,
            'inclusive'    => $request->boolean('inclusive', true),
            'enabled'      => $request->boolean('enabled'),
            'sort_order'   => $request->sort_order ?? 0,
        ];
    }
}
