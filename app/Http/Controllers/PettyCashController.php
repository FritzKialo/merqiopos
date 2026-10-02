<?php

namespace App\Http\Controllers;

use App\Models\PettyCashAccount;
use App\Models\PettyCashTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PettyCashController extends Controller
{
    private function businessId(): int
    {
        return Auth::user()->currentBusiness()->id;
    }

    private function getAccount(): PettyCashAccount
    {
        return PettyCashAccount::firstOrCreate(
            ['business_id' => $this->businessId()],
            ['name' => 'Petty Cash', 'current_balance' => 0]
        );
    }

    public function index()
    {
        $account = $this->getAccount();
        $transactions = PettyCashTransaction::where('petty_cash_account_id', $account->id)
            ->with('user')
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20);

        // Chart data: last 30 days running balance
        $chartData = PettyCashTransaction::where('petty_cash_account_id', $account->id)
            ->where('transaction_date', '>=', now()->subDays(30)->toDateString())
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get(['transaction_date', 'balance_after', 'type', 'amount'])
            ->groupBy(fn ($t) => $t->transaction_date->format('d M'))
            ->map(fn ($group) => $group->last()->balance_after);

        $categories = PettyCashTransaction::where('petty_cash_account_id', $account->id)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        return view('petty-cash.index', compact('account', 'transactions', 'chartData', 'categories'));
    }

    public function topup(Request $request)
    {
        $request->validate([
            'amount'           => 'required|numeric|min:0.01',
            'description'      => 'required|string|max:255',
            'reference'        => 'nullable|string|max:100',
            'transaction_date' => 'required|date',
        ]);

        $account = $this->getAccount();

        DB::transaction(function () use ($request, $account) {
            $newBalance = (float) $account->current_balance + (float) $request->amount;

            PettyCashTransaction::create([
                'business_id'          => $this->businessId(),
                'user_id'              => Auth::id(),
                'petty_cash_account_id'=> $account->id,
                'type'                 => 'topup',
                'amount'               => $request->amount,
                'description'          => $request->description,
                'reference'            => $request->reference,
                'transaction_date'     => $request->transaction_date,
                'balance_after'        => $newBalance,
            ]);

            $account->update(['current_balance' => $newBalance]);
        });

        return back()->with('success', 'Petty cash topped up successfully.');
    }

    public function disburse(Request $request)
    {
        $account = $this->getAccount();

        $request->validate([
            'amount'           => 'required|numeric|min:0.01|max:' . $account->current_balance,
            'description'      => 'required|string|max:255',
            'category'         => 'nullable|string|max:100',
            'receipt_number'   => 'nullable|string|max:50',
            'reference'        => 'nullable|string|max:100',
            'transaction_date' => 'required|date',
        ]);

        DB::transaction(function () use ($request, $account) {
            $newBalance = max(0, (float) $account->current_balance - (float) $request->amount);

            PettyCashTransaction::create([
                'business_id'          => $this->businessId(),
                'user_id'              => Auth::id(),
                'petty_cash_account_id'=> $account->id,
                'type'                 => 'disbursement',
                'amount'               => $request->amount,
                'description'          => $request->description,
                'category'             => $request->category,
                'receipt_number'       => $request->receipt_number,
                'reference'            => $request->reference,
                'transaction_date'     => $request->transaction_date,
                'balance_after'        => $newBalance,
            ]);

            $account->update(['current_balance' => $newBalance]);
        });

        return back()->with('success', 'Disbursement recorded.');
    }
}
