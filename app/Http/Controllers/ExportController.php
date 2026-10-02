<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\PayrollPeriod;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExportController extends Controller
{
    private function business()
    {
        return Auth::user()->currentBusiness();
    }

    public function index()
    {
        return view('settings.exports');
    }

    public function quickbooks(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date',
        ]);

        $business = $this->business();
        $from     = $request->from ?? now()->startOfMonth()->toDateString();
        $to       = $request->to   ?? now()->toDateString();

        $rows = [];

        // Sales
        $sales = Sale::forBusiness($business->id)
            ->where('sale_status', 'completed')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->with('customer')
            ->get();

        foreach ($sales as $s) {
            $name = $s->customer?->name ?? 'Walk-in';
            $rows[] = [$s->created_at->toDateString(), 'INVOICE', 'Accounts Receivable', $s->total_amount, '', $s->invoice_number, $name];
            $rows[] = [$s->created_at->toDateString(), 'INVOICE', 'Sales Revenue',        '', $s->total_amount, $s->invoice_number, $name];
        }

        // Expenses
        $expenses = Expense::forBusiness($business->id)
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to)
            ->with('category')
            ->get();

        foreach ($expenses as $e) {
            $cat = $e->category?->name ?? 'General Expense';
            $rows[] = [$e->expense_date->toDateString(), 'EXPENSE', $cat,  $e->amount, '', $e->title ?? $cat, ''];
            $rows[] = [$e->expense_date->toDateString(), 'EXPENSE', 'Cash', '', $e->amount, $e->title ?? $cat, ''];
        }

        // Payroll
        $payrolls = PayrollPeriod::where('business_id', $business->id)
            ->where('status', 'paid')
            ->whereDate('paid_at', '>=', $from)
            ->whereDate('paid_at', '<=', $to)
            ->get();

        foreach ($payrolls as $p) {
            $label = 'Payroll ' . ($p->period_label ?? $p->month ?? $p->created_at->format('M Y'));
            $total = $p->total_net ?? $p->items()->sum('net_pay');
            $rows[] = [$p->paid_at->toDateString(), 'PAYROLL', 'Salary Expense', $total, '', $label, ''];
            $rows[] = [$p->paid_at->toDateString(), 'PAYROLL', 'Cash',           '', $total, $label, ''];
        }

        $headers = ['Date', 'Type', 'Account', 'Debit', 'Credit', 'Memo', 'Name'];
        $filename = "quickbooks_journal_{$from}_{$to}.csv";

        return $this->csvResponse($filename, $headers, $rows);
    }

    public function xero(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date',
        ]);

        $business = $this->business();
        $from     = $request->from ?? now()->startOfMonth()->toDateString();
        $to       = $request->to   ?? now()->toDateString();

        $rows = [];

        // Sales
        $sales = Sale::forBusiness($business->id)
            ->where('sale_status', 'completed')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->with('customer')
            ->get();

        foreach ($sales as $s) {
            $name = $s->customer?->name ?? 'Walk-in Customer';
            $due  = $s->created_at->addDays(30)->toDateString();
            $rows[] = [
                $name,
                $s->invoice_number,
                $s->created_at->toDateString(),
                $due,
                'Sales Revenue',
                1,
                $s->total_amount,
                200,
                'OUTPUT',
            ];
        }

        // Expenses
        $expenses = Expense::forBusiness($business->id)
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to)
            ->with('category')
            ->get();

        foreach ($expenses as $e) {
            $rows[] = [
                $business->name,
                'EXP-' . $e->id,
                $e->expense_date->toDateString(),
                $e->expense_date->toDateString(),
                $e->title ?? ($e->category?->name ?? 'Expense'),
                1,
                $e->amount,
                400,
                'NONE',
            ];
        }

        // Payroll
        $payrolls = PayrollPeriod::where('business_id', $business->id)
            ->where('status', 'paid')
            ->whereDate('paid_at', '>=', $from)
            ->whereDate('paid_at', '<=', $to)
            ->get();

        foreach ($payrolls as $p) {
            $label = 'Payroll ' . ($p->period_label ?? $p->month ?? $p->created_at->format('M Y'));
            $total = $p->total_net ?? $p->items()->sum('net_pay');
            $rows[] = [
                $business->name,
                'PAY-' . $p->id,
                $p->paid_at->toDateString(),
                $p->paid_at->toDateString(),
                $label,
                1,
                $total,
                477,
                'NONE',
            ];
        }

        $headers = [
            '*ContactName', '*InvoiceNumber', 'InvoiceDate', '*DueDate',
            '*Description', '*Quantity', '*UnitAmount', '*AccountCode', '*TaxType',
        ];
        $filename = "xero_export_{$from}_{$to}.csv";

        return $this->csvResponse($filename, $headers, $rows);
    }

    private function csvResponse(string $filename, array $headers, array $rows)
    {
        $callback = function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            \App\Support\Csv::put($handle, $headers);
            foreach ($rows as $row) {
                \App\Support\Csv::put($handle, $row);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
