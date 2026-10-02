<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShiftController extends Controller
{
    private function business()
    {
        return Auth::user()->currentBusiness();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index()
    {
        $business = $this->business();

        $shifts = Shift::with(['opener', 'closer'])
            ->forBusiness($business->id)
            ->latest('opened_at')
            ->paginate(20);

        $openShift = Shift::forBusiness($business->id)->where('status', 'open')->first();

        return view('shifts.index', compact('shifts', 'openShift'));
    }

    // ── Open GET ──────────────────────────────────────────────────────────────

    public function open()
    {
        $business  = $this->business();
        $openShift = Shift::forBusiness($business->id)->where('status', 'open')->first();

        if ($openShift) {
            return redirect()->route('shifts.show', $openShift)
                ->with('error', 'There is already an open shift.');
        }

        return view('shifts.open');
    }

    // ── Open POST ─────────────────────────────────────────────────────────────

    public function openStore(Request $request)
    {
        $business = $this->business();

        $openShift = Shift::forBusiness($business->id)->where('status', 'open')->first();
        if ($openShift) {
            return redirect()->route('shifts.show', $openShift)
                ->with('error', 'There is already an open shift.');
        }

        $request->validate([
            'opening_float' => 'required|numeric|min:0',
            'notes'         => 'nullable|string|max:500',
        ]);

        $shift = Shift::create([
            'business_id'   => $business->id,
            'opened_by'     => Auth::id(),
            'opening_float' => $request->opening_float,
            'status'        => 'open',
            'opened_at'     => now(),
            'notes'         => $request->notes,
        ]);

        return redirect()->route('shifts.show', $shift)
            ->with('success', 'Shift opened successfully.');
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(Shift $shift)
    {
        $business = $this->business();

        if ($shift->business_id !== $business->id) {
            abort(403);
        }

        $shift->load(['opener', 'closer']);

        // Actual sales rung up during this shift, now that they're stamped
        // with shift_id directly rather than inferred from a time window.
        // Sales predating this feature (shift_id null) simply won't show
        // here — see the comment in close() for why that's fine.
        $sales = $shift->sales()
            ->with('customer')
            ->where('sale_status', '!=', 'cancelled')
            ->latest()
            ->get();

        return view('shifts.show', compact('shift', 'sales'));
    }

    // ── Close GET ─────────────────────────────────────────────────────────────

    public function close(Shift $shift)
    {
        $business = $this->business();

        if ($shift->business_id !== $business->id) {
            abort(403);
        }

        if (! $shift->isOpen()) {
            return redirect()->route('shifts.show', $shift)->with('error', 'Shift is already closed.');
        }

        // Sales summary for this shift — keyed off shift_id (stamped on
        // every sale at creation time by Shift::currentOpenId(), see
        // SaleService et al.) rather than a created_at >= opened_at time
        // window. The old window-based approach silently over-counted any
        // shift left open across a long span (or overlapping with a
        // previous unclosed shift), attributing sales to a shift they
        // never actually happened during. Sales created before this
        // migration have shift_id = null and simply won't appear here —
        // by the time this shipped, any such shift was already closed
        // with its totals frozen on the shift row itself, so nothing here
        // needs to account for them.
        $salesQuery = fn () => Sale::where('business_id', $business->id)
            ->where('shift_id', $shift->id)
            ->where('sale_status', '!=', 'cancelled');

        $totalSales        = $salesQuery()->sum('total_amount');
        // paid_amount, not total_amount — a partially-paid cash sale (e.g.
        // KSh 100 of a KSh 200 total) only ever put KSh 100 in the drawer;
        // summing total_amount counted the still-unpaid balance as if it
        // were physically in the till, inflating expected cash by exactly
        // the amount still owed on every partial cash sale.
        $cashSales         = $salesQuery()->where('payment_method', 'cash')->sum('paid_amount');
        $mpesaSales        = $salesQuery()->where('payment_method', 'mpesa')->sum('paid_amount');
        $cardSales         = $salesQuery()->where('payment_method', 'card')->sum('paid_amount');
        $totalTransactions = $salesQuery()->count();

        $expectedCash = (float) $shift->opening_float + (float) $cashSales;

        // Per-cashier Digital Float settlement — only meaningful once the
        // business has opted in (see Settings > Digital Float). Shown here
        // rather than a separate screen so settlement happens right where
        // shifts already get closed.
        $floatBreakdown = collect();
        if ($business->enable_digital_float) {
            $floatService = new \App\Services\FloatService();
            $floatBreakdown = $business->users()->wherePivot('role', 'cashier')->orderBy('name')->get()
                ->map(fn ($u) => $floatService->breakdown($u, $business, $shift->id))
                // Only show cashiers who actually did something this shift —
                // an unused credit limit sitting at zero owed isn't worth a row.
                ->filter(fn ($row) => $row['cash_sales'] > 0 || $row['deposits'] > 0 || $row['refloats'] > 0);
        }

        return view('shifts.close', compact(
            'shift', 'totalSales', 'cashSales', 'mpesaSales',
            'cardSales', 'totalTransactions', 'expectedCash',
            'business', 'floatBreakdown'
        ));
    }

    // ── Close POST ────────────────────────────────────────────────────────────

    public function closeStore(Request $request, Shift $shift)
    {
        $business = $this->business();

        if ($shift->business_id !== $business->id) {
            abort(403);
        }

        if (! $shift->isOpen()) {
            return redirect()->route('shifts.show', $shift)->with('error', 'Shift is already closed.');
        }

        $request->validate([
            'closing_cash' => 'required|numeric|min:0',
            'notes'        => 'nullable|string|max:500',
        ]);

        // Same shift_id-based approach as close() above.
        $salesQuery = fn() => Sale::where('business_id', $business->id)
            ->where('shift_id', $shift->id)
            ->where('sale_status', '!=', 'cancelled');

        $totalSales       = $salesQuery()->sum('total_amount');
        // See the matching comment in close() above — paid_amount, not
        // total_amount, is what actually reached the till.
        $cashSales        = $salesQuery()->where('payment_method', 'cash')->sum('paid_amount');
        $mpesaSales       = $salesQuery()->where('payment_method', 'mpesa')->sum('paid_amount');
        $cardSales        = $salesQuery()->where('payment_method', 'card')->sum('paid_amount');
        $totalTransactions= $salesQuery()->count();

        $expectedCash  = (float) $shift->opening_float + (float) $cashSales;
        $closingCash   = (float) $request->closing_cash;
        $variance      = $closingCash - $expectedCash;

        $shift->update([
            'closed_by'           => Auth::id(),
            'closing_cash'        => $closingCash,
            'expected_cash'       => $expectedCash,
            'cash_variance'       => $variance,
            'total_sales'         => $totalSales,
            'total_cash_sales'    => $cashSales,
            'total_mpesa_sales'   => $mpesaSales,
            'total_card_sales'    => $cardSales,
            'total_transactions'  => $totalTransactions,
            'status'              => 'closed',
            'closed_at'           => now(),
            'notes'               => $request->notes ?? $shift->notes,
        ]);

        return redirect()->route('shifts.show', $shift)
            ->with('success', 'Shift closed successfully.');
    }
}
