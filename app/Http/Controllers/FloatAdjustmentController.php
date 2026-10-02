<?php

namespace App\Http\Controllers;

use App\Models\FloatAdjustment;
use App\Models\Shift;
use App\Models\User;
use App\Services\FloatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FloatAdjustmentController extends Controller {

    private function business() {
        return Auth::user()->currentBusiness();
    }

    // ── Cash Deposits: record a deposit or refloat for a cashier during the
    // business's currently open shift, and see everyone's running balance.
    public function index() {
        $business = $this->business();
        abort_unless($business->enable_digital_float, 404);

        $shiftId  = Shift::currentOpenId($business->id);
        $cashiers = $business->users()->wherePivot('role', 'cashier')->orderBy('name')->get();

        $floatService = new FloatService();
        $breakdown = $shiftId
            ? $cashiers->map(fn (User $u) => $floatService->breakdown($u, $business, $shiftId))
            : collect();

        return view('cash-deposits.index', compact('cashiers', 'breakdown', 'shiftId'));
    }

    public function store(Request $request) {
        $business = $this->business();
        abort_unless($business->enable_digital_float, 404);

        $shiftId = Shift::currentOpenId($business->id);
        if (!$shiftId) {
            return back()->with('error', 'There is no open shift right now — deposits and refloats only apply while a shift is open.');
        }

        $request->validate([
            'user_id' => ['required', 'integer', function ($attr, $value, $fail) use ($business) {
                if (!$business->users()->wherePivot('role', 'cashier')->where('users.id', $value)->exists()) {
                    $fail('That person is not a cashier on this business.');
                }
            }],
            'type'    => 'required|in:deposit,refloat',
            'amount'  => 'required|numeric|min:0.01',
            'notes'   => 'nullable|string|max:500',
        ]);

        // A deposit is cash handed over against what the cashier owes — one
        // larger than that left "Amount Owed" negative and pushed their
        // available float above their limit.
        if ($request->type === 'deposit') {
            $owed = (new FloatService())->shortfall($shiftId, (int) $request->user_id);
            if ((float) $request->amount > $owed + 0.001) {
                return back()->withInput()->with('error', 'That deposit is more than this cashier currently owes (KSh ' . number_format($owed, 2) . '). Record a refloat instead if you want to raise their allowance.');
            }
        }

        $floatRow = FloatAdjustment::create([
            'business_id' => $business->id,
            'shift_id'    => $shiftId,
            'user_id'     => $request->user_id,
            'type'        => $request->type,
            'amount'      => $request->amount,
            'recorded_by' => Auth::id(),
            'notes'       => $request->notes,
        ]);

        \App\Models\AuditLog::record('float.' . $request->type, $floatRow, ['amount' => (float) $request->amount, 'cashier_id' => $request->user_id, 'notes' => $request->notes]);

        $label = $request->type === 'deposit' ? 'Deposit' : 'Refloat';
        return back()->with('success', "{$label} of KSh " . number_format($request->amount, 2) . ' recorded.');
    }
}
