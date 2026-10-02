<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VatReturnController extends Controller
{
    private function businessId() { return Auth::user()->currentBusiness()->id; }

    public function index(Request $request)
    {
        $business = Auth::user()->currentBusiness();
        $month = (int) $request->get('month', now()->month);
        $year  = (int) $request->get('year',  now()->year);
        $start = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end   = \Carbon\Carbon::createFromDate($year, $month, 1)->endOfMonth();

        // Output VAT — tax collected on sales/invoices.
        // Was: SUM(total_amount) - SUM(total_amount / 1.16) — a hardcoded 16%
        // assumption applied to every sale, regardless of whether the
        // business is even VAT-registered, its actual configured vat_rate,
        // or per-product VAT exemptions. sales.tax_amount is already computed
        // correctly per-sale at creation time (SaleService::createSale,
        // respecting isVatRegistered() + the business's real rate) — a
        // non-VAT-registered business's sales correctly have tax_amount=0,
        // but the old formula would still report a fake ~13.8% VAT liability
        // on their full gross sales, wildly overstating what they owe KRA.
        $salesVat = DB::table('sales')
            ->where('business_id', $this->businessId())
            ->where('sale_status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('SUM(total_amount) as gross, SUM(tax_amount) as vat_amount, COUNT(*) as count')
            ->first();

        $invoiceVat = DB::table('invoices')
            ->where('business_id', $this->businessId())
            // Invoice statuses are draft/sent/partial/paid/cancelled — 'partially_paid'
            // has never existed, so every partially-paid invoice's VAT was silently
            // left out of the return. Exclude only what genuinely isn't a tax point.
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('SUM(total) as gross, SUM(vat_amount) as vat_amount, COUNT(*) as count')
            ->first();

        // Input VAT — tax paid on purchases.
        // Was: whereIn('status', ['received','partial']) — 'partial' has
        // never been a real purchase_orders.status value (the enum is
        // draft/ordered/partially_received/received/cancelled), so every
        // partially-received PO's tax was silently excluded from input VAT.
        $purchaseVat = DB::table('purchase_orders')
            ->where('business_id', $this->businessId())
            ->whereIn('status', ['received','partially_received'])
            ->whereBetween('order_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('SUM(total) as gross, SUM(tax_amount) as vat_amount, COUNT(*) as count')
            ->first();

        $outputVat = ($salesVat->vat_amount ?? 0) + ($invoiceVat->vat_amount ?? 0);
        $inputVat  = $purchaseVat->vat_amount ?? 0;
        $netVat    = $outputVat - $inputVat;
        $period    = \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y');

        if ($request->get('export') === 'csv') {
            return $this->exportCsv($period, $salesVat, $invoiceVat, $purchaseVat, $outputVat, $inputVat, $netVat, $month, $year);
        }

        return view('reports.vat-return', compact(
            'salesVat','invoiceVat','purchaseVat','outputVat','inputVat','netVat','period','month','year','business'
        ));
    }

    private function exportCsv($period, $salesVat, $invoiceVat, $purchaseVat, $outputVat, $inputVat, $netVat, $month, $year)
    {
        $filename = "VAT_Return_{$year}_{$month}.csv";
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"$filename\""];
        $callback = function() use ($period, $salesVat, $invoiceVat, $purchaseVat, $outputVat, $inputVat, $netVat) {
            $out = fopen('php://output', 'w');
            \App\Support\Csv::put($out, ['KRA VAT Return Summary', $period]);
            \App\Support\Csv::put($out, []);
            \App\Support\Csv::put($out, ['SECTION', 'TRANSACTIONS', 'GROSS AMOUNT (KSh)', 'VAT AMOUNT (KSh)']);
            \App\Support\Csv::put($out, ['OUTPUT VAT (Tax Collected)','','','']);
            \App\Support\Csv::put($out, ['Sales (POS)', $salesVat->count ?? 0, number_format($salesVat->gross ?? 0, 2), number_format($salesVat->vat_amount ?? 0, 2)]);
            \App\Support\Csv::put($out, ['Invoices', $invoiceVat->count ?? 0, number_format($invoiceVat->gross ?? 0, 2), number_format($invoiceVat->vat_amount ?? 0, 2)]);
            \App\Support\Csv::put($out, ['Total Output VAT', '', '', number_format($outputVat, 2)]);
            \App\Support\Csv::put($out, []);
            \App\Support\Csv::put($out, ['INPUT VAT (Tax Paid on Purchases)','','','']);
            \App\Support\Csv::put($out, ['Purchase Orders', $purchaseVat->count ?? 0, number_format($purchaseVat->gross ?? 0, 2), number_format($purchaseVat->vat_amount ?? 0, 2)]);
            \App\Support\Csv::put($out, ['Total Input VAT', '', '', number_format($inputVat, 2)]);
            \App\Support\Csv::put($out, []);
            \App\Support\Csv::put($out, ['NET VAT PAYABLE TO KRA', '', '', number_format($netVat, 2)]);
            fclose($out);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function export(Request $request)
    {
        return $this->index($request->merge(['export' => 'csv']));
    }
}
