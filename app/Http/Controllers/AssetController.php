<?php

namespace App\Http\Controllers;

use App\Models\BusinessAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssetController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    public function index() {
        $businessId = $this->businessId();

        $assets = BusinessAsset::forBusiness($businessId)
            ->orderBy('status')
            ->orderByDesc('purchase_date')
            ->get();

        $totalPortfolioValue = $assets->where('status', 'active')->sum('current_value');
        $totalCost           = $assets->where('status', 'active')->sum('purchase_cost');
        $totalDepreciation   = $totalCost - $totalPortfolioValue;

        // Depreciation this year
        $annualDepreciation = $assets->where('status', 'active')
            ->sum(fn($a) => $a->annualDepreciation());

        return view('assets.index', compact(
            'assets', 'totalPortfolioValue', 'totalDepreciation', 'annualDepreciation'
        ));
    }

    public function create() {
        return view('assets.create');
    }

    public function store(Request $request) {
        $businessId = $this->businessId();

        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'category'            => 'required|string|max:100',
            'description'         => 'nullable|string',
            'purchase_date'       => 'required|date',
            'purchase_cost'       => 'required|numeric|min:0',
            'depreciation_method' => 'required|in:straight_line,none',
            'useful_life_years'   => 'required|integer|min:1',
            'residual_value'      => 'nullable|numeric|min:0',
            'notes'               => 'nullable|string',
        ]);

        $validated['business_id']   = $businessId;
        $validated['user_id']       = Auth::id();
        $validated['residual_value'] = $validated['residual_value'] ?? 0;
        $validated['current_value'] = 0; // will be set by model event

        DB::transaction(function () use ($validated) {
            BusinessAsset::create($validated);
        });

        return redirect()->route('assets.index')->with('success', 'Asset added.');
    }

    public function edit(BusinessAsset $asset) {
        if ($asset->business_id !== $this->businessId()) abort(403);
        return view('assets.edit', compact('asset'));
    }

    public function update(Request $request, BusinessAsset $asset) {
        if ($asset->business_id !== $this->businessId()) abort(403);

        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'category'            => 'required|string|max:100',
            'description'         => 'nullable|string',
            'purchase_date'       => 'required|date',
            'purchase_cost'       => 'required|numeric|min:0',
            'depreciation_method' => 'required|in:straight_line,none',
            'useful_life_years'   => 'required|integer|min:1',
            'residual_value'      => 'nullable|numeric|min:0',
            'notes'               => 'nullable|string',
        ]);

        $validated['residual_value'] = $validated['residual_value'] ?? 0;

        DB::transaction(function () use ($asset, $validated) {
            $asset->update($validated);
        });

        return redirect()->route('assets.index')->with('success', 'Asset updated.');
    }

    public function dispose(Request $request, BusinessAsset $asset) {
        if ($asset->business_id !== $this->businessId()) abort(403);

        $validated = $request->validate([
            'disposal_date'     => 'required|date',
            'disposal_proceeds' => 'nullable|numeric|min:0',
            'notes'             => 'nullable|string',
        ]);

        DB::transaction(function () use ($asset, $validated) {
            $asset->update([
                'status'            => 'disposed',
                'disposal_date'     => $validated['disposal_date'],
                'disposal_proceeds' => $validated['disposal_proceeds'] ?? 0,
                'notes'             => $validated['notes'] ?? $asset->notes,
            ]);
        });

        $proceeds = (float) ($validated['disposal_proceeds'] ?? 0);
        $bookValue = (float) $asset->current_value;
        $gainLoss = $proceeds - $bookValue;
        $msg = $gainLoss >= 0
            ? 'Asset disposed. Gain: KSh ' . number_format($gainLoss, 2)
            : 'Asset disposed. Loss: KSh ' . number_format(abs($gainLoss), 2);

        return redirect()->route('assets.index')->with('success', $msg);
    }

    public function destroy(BusinessAsset $asset) {
        if ($asset->business_id !== $this->businessId()) abort(403);
        $asset->delete();
        return redirect()->route('assets.index')->with('success', 'Asset deleted.');
    }
}
