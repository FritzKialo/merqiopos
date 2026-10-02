<?php
namespace App\Http\Controllers;
use App\Models\WithholdingTax;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WithholdingTaxController extends Controller {
    private function businessId() { return Auth::user()->currentBusiness()->id; }

    public function index(Request $request) {
        $query = WithholdingTax::where('business_id', $this->businessId())->with('supplier')->latest();
        if ($request->month) $query->whereMonth('payment_date', $request->month);
        if ($request->year)  $query->whereYear('payment_date', $request->year);
        $records = $query->paginate(20);
        $totalWht = WithholdingTax::where('business_id', $this->businessId())->whereYear('payment_date', now()->year)->sum('wht_amount');

        if ($request->get('export') === 'csv') {
            $all = $query->get();
            $filename = 'WHT_' . now()->year . '.csv';
            $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"$filename\""];
            $cb = function() use ($all) {
                $out = fopen('php://output', 'w');
                \App\Support\Csv::put($out, ['Payee','KRA PIN','Type','Gross','Rate','WHT','Net','Date']);
                foreach ($all as $r) \App\Support\Csv::put($out, [$r->payee_name,$r->payee_kra_pin??'N/A',$r->wht_type,number_format($r->gross_amount,2),$r->wht_rate.'%',number_format($r->wht_amount,2),number_format($r->net_amount,2),$r->payment_date->format('d/m/Y')]);
                fclose($out);
            };
            return response()->stream($cb, 200, $headers);
        }

        return view('withholding-tax.index', compact('records', 'totalWht'));
    }

    public function create() {
        $suppliers = Supplier::where('business_id', $this->businessId())->orderBy('name')->get();
        $rates = WithholdingTax::whtRates();
        return view('withholding-tax.create', compact('suppliers', 'rates'));
    }

    public function store(Request $request) {
        $businessId = $this->businessId();
        $request->validate([
            'payee_name'   => 'required|string|max:200',
            'payee_kra_pin'=> 'nullable|string|max:20',
            'wht_type'     => 'required|string',
            'gross_amount' => 'required|numeric|min:0',
            'wht_rate'     => 'required|numeric|min:0|max:100',
            'payment_date' => 'required|date',
            // supplier_id was previously unscoped — could reference another
            // business's supplier record.
            'supplier_id'  => ['nullable', \Illuminate\Validation\Rule::exists('suppliers', 'id')->where('business_id', $businessId)],
        ]);
        $gross  = $request->gross_amount;
        $rate   = $request->wht_rate;
        $whtAmt = round($gross * $rate / 100, 2);
        WithholdingTax::create([
            'business_id'     => $this->businessId(),
            'supplier_id'     => $request->supplier_id,
            'payee_name'      => $request->payee_name,
            'payee_kra_pin'   => $request->payee_kra_pin,
            'wht_type'        => $request->wht_type,
            'gross_amount'    => $gross,
            'wht_rate'        => $rate,
            'wht_amount'      => $whtAmt,
            'net_amount'      => $gross - $whtAmt,
            'payment_date'    => $request->payment_date,
            'certificate_number' => $request->certificate_number,
            'notes'           => $request->notes,
        ]);
        return redirect()->route('withholding-tax.index')->with('success', 'WHT record created. KSh ' . number_format($whtAmt, 2) . ' to remit to KRA.');
    }

    public function show(WithholdingTax $wht) {
        // Every other controller in this app checks business ownership before
        // show/destroy — this one didn't, at all. Any logged-in user of ANY
        // business could view another business's withholding-tax records
        // (real KRA tax/compliance data) just by guessing the numeric id.
        abort_if($wht->business_id !== $this->businessId(), 403);
        return view('withholding-tax.show', compact('wht'));
    }

    public function destroy(WithholdingTax $wht) {
        // Same gap as show() — worse here, since it let any business
        // permanently delete another business's tax compliance record.
        abort_if($wht->business_id !== $this->businessId(), 403);
        $wht->delete();
        return redirect()->route('withholding-tax.index')->with('success', 'Deleted.');
    }

    public function export(Request $request) {
        return $this->index($request->merge(['export' => 'csv']));
    }
}
