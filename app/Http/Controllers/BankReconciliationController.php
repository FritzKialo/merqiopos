<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankStatementImport;
use App\Models\BankStatementLine;
use App\Models\Expense;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BankReconciliationController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    // ── List bank accounts ────────────────────────────────────────────────
    public function index() {
        $businessId = $this->businessId();
        $accounts   = BankAccount::forBusiness($businessId)->get();

        // Eager-load unreconciled counts
        $accounts->each(fn($a) => $a->unreconciled = $a->unreconciledCount());

        return view('bank-reconciliation.index', compact('accounts'));
    }

    // ── Create account ────────────────────────────────────────────────────
    public function createAccount(Request $request) {
        abort_if(!Auth::user()->canActAsOwner(), 403);

        $request->validate([
            'name'            => 'required|string|max:100',
            'account_number'  => 'nullable|string|max:50',
            'bank_name'       => 'nullable|string|max:100',
            'current_balance' => 'nullable|numeric',
        ]);

        BankAccount::create([
            'business_id'     => $this->businessId(),
            'name'            => $request->name,
            'account_number'  => $request->account_number,
            'bank_name'       => $request->bank_name,
            'current_balance' => $request->current_balance ?? 0,
        ]);

        return back()->with('success', 'Bank account added.');
    }

    // ── Import CSV statement ──────────────────────────────────────────────
    public function import(BankAccount $account, Request $request) {
        abort_if($account->business_id !== $this->businessId(), 403);

        $request->validate([
            'statement' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $file    = $request->file('statement');
        $handle  = fopen($file->getRealPath(), 'r');
        $headers = null;
        $lines   = [];
        $errors  = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (!$headers) {
                $headers = array_map('strtolower', array_map('trim', $row));
                continue;
            }
            if (count($row) < 3) continue;

            $mapped = array_combine(array_slice($headers, 0, count($row)), $row);

            $dateStr = $mapped['date'] ?? $mapped['transaction date'] ?? $mapped['trans date'] ?? null;
            $desc    = $mapped['description'] ?? $mapped['narration'] ?? $mapped['details'] ?? '';
            $debit   = $this->parseMoney($mapped['debit'] ?? $mapped['withdrawal'] ?? null);
            $credit  = $this->parseMoney($mapped['credit'] ?? $mapped['deposit'] ?? null);
            $balance = $this->parseMoney($mapped['balance'] ?? $mapped['running balance'] ?? null);
            $ref     = $mapped['reference'] ?? $mapped['ref'] ?? null;

            if (!$dateStr) { $errors[] = "Row skipped: no date"; continue; }

            try {
                $date = Carbon::parse($dateStr)->toDateString();
            } catch (\Exception) {
                $errors[] = "Row skipped: invalid date '{$dateStr}'";
                continue;
            }

            $lines[] = [
                'date'        => $date,
                'description' => mb_substr(trim($desc), 0, 499),
                'reference'   => $ref ? mb_substr(trim($ref), 0, 99) : null,
                'debit'       => $debit,
                'credit'      => $credit,
                'balance'     => $balance,
            ];
        }
        fclose($handle);

        if (empty($lines)) {
            return back()->with('error', 'No valid rows found in the CSV file.');
        }

        DB::transaction(function () use ($account, $file, $lines) {
            $dates = array_column($lines, 'date');

            $import = BankStatementImport::create([
                'business_id'    => $account->business_id,
                'bank_account_id'=> $account->id,
                'user_id'        => Auth::id(),
                'filename'       => $file->getClientOriginalName(),
                'period_start'   => min($dates),
                'period_end'     => max($dates),
                'imported_at'    => now(),
                'total_credits'  => array_sum(array_column($lines, 'credit')),
                'total_debits'   => array_sum(array_column($lines, 'debit')),
            ]);

            foreach ($lines as $line) {
                // Auto-match against sales and expenses
                $matchedType = null;
                $matchedId   = null;

                if ($line['credit']) {
                    $sale = Sale::where('business_id', $account->business_id)
                        ->whereDate('created_at', $line['date'])
                        ->where('total_amount', $line['credit'])
                        ->first();
                    if ($sale) { $matchedType = 'sale'; $matchedId = $sale->id; }
                }

                if (!$matchedType && $line['debit']) {
                    $expense = Expense::where('business_id', $account->business_id)
                        ->whereDate('expense_date', $line['date'])
                        ->where('amount', $line['debit'])
                        ->first();
                    if ($expense) { $matchedType = 'expense'; $matchedId = $expense->id; }
                }

                BankStatementLine::create([
                    'bank_statement_import_id' => $import->id,
                    'bank_account_id'          => $account->id,
                    'transaction_date'         => $line['date'],
                    'description'              => $line['description'],
                    'reference'                => $line['reference'],
                    'debit'                    => $line['debit'],
                    'credit'                   => $line['credit'],
                    'balance'                  => $line['balance'],
                    'matched_type'             => $matchedType,
                    'matched_id'               => $matchedId,
                    'is_reconciled'            => (bool) $matchedType,
                ]);
            }
        });

        return redirect()->route('bank-reconciliation.lines', $account)
            ->with('success', count($lines) . ' statement lines imported.');
    }

    // ── Show lines for an account ─────────────────────────────────────────
    public function lines(BankAccount $account, Request $request) {
        abort_if($account->business_id !== $this->businessId(), 403);

        $lines = BankStatementLine::where('bank_account_id', $account->id)
            ->orderBy('transaction_date')
            ->paginate(50)
            ->withQueryString();

        return view('bank-reconciliation.lines', compact('account', 'lines'));
    }

    // ── Reconcile a line ──────────────────────────────────────────────────
    public function reconcile(BankStatementLine $line, Request $request) {
        abort_if($line->bankAccount->business_id !== $this->businessId(), 403);

        $request->validate([
            'matched_type' => 'nullable|in:sale,expense,payroll',
            'matched_id'   => 'nullable|integer',
        ]);

        // matched_id was stored as-is — any integer, including another
        // business's sale or expense. Only accept records from this business.
        if ($request->matched_id) {
            $bid = $this->businessId();
            $owned = match ($request->matched_type) {
                'sale'    => Sale::where('business_id', $bid)->whereKey($request->matched_id)->exists(),
                'expense' => Expense::where('business_id', $bid)->whereKey($request->matched_id)->exists(),
                'payroll' => \App\Models\PayrollItem::where('business_id', $bid)->whereKey($request->matched_id)->exists(),
                default   => false,
            };
            if (!$owned) {
                return back()->with('error', 'That record was not found in your business.');
            }
        }

        $line->update([
            'matched_type'  => $request->matched_type,
            'matched_id'    => $request->matched_id,
            'is_reconciled' => true,
        ]);

        return back()->with('success', 'Line marked as reconciled.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────
    private function parseMoney(?string $value): ?float {
        if ($value === null || trim($value) === '') return null;
        $cleaned = preg_replace('/[^\d.-]/', '', $value);
        return $cleaned !== '' ? (float) $cleaned : null;
    }
}
