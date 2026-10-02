<?php
namespace App\Http\Controllers;
use App\Models\CashRegister;
use App\Models\CashRegisterEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CashRegisterController extends Controller {
    private function businessId() { return Auth::user()->currentBusiness()->id; }

    public function index() {
        $registers = CashRegister::where('business_id', $this->businessId())
            ->with('user')
            ->latest('opened_at')
            ->paginate(20);
        $openRegister = CashRegister::where('business_id', $this->businessId())
            ->where('status', 'open')
            ->with('entries')
            ->first();
        return view('cash-registers.index', compact('registers', 'openRegister'));
    }

    public function openForm() {
        // Check if there's already an open register today
        $existing = CashRegister::where('business_id', $this->businessId())
            ->where('status', 'open')
            ->first();
        if ($existing) {
            return redirect()->route('cash-registers.show', $existing)
                ->with('error', 'There is already an open register session.');
        }
        return view('cash-registers.open');
    }

    public function open(Request $request) {
        $request->validate([
            'opening_float' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $existing = CashRegister::where('business_id', $this->businessId())
            ->where('status', 'open')
            ->first();
        if ($existing) {
            return redirect()->route('cash-registers.show', $existing)
                ->with('error', 'A register session is already open.');
        }

        $register = CashRegister::create([
            'business_id' => $this->businessId(),
            'user_id' => Auth::id(),
            'opened_at' => now(),
            'opening_float' => $request->opening_float,
            'expected_closing' => $request->opening_float,
            'status' => 'open',
            'notes' => $request->notes,
        ]);

        return redirect()->route('cash-registers.show', $register)
            ->with('success', 'Register opened with KSh ' . number_format($request->opening_float, 2) . ' float.');
    }

    public function current() {
        $register = CashRegister::where('business_id', $this->businessId())
            ->where('status', 'open')
            ->first();
        if (!$register) {
            return redirect()->route('cash-registers.open-form')
                ->with('error', 'No open register. Please open a new session.');
        }
        return redirect()->route('cash-registers.show', $register);
    }

    public function show(CashRegister $register) {
        // Same gap as WithholdingTaxController earlier this session — no
        // business-ownership check at all, and no route-level scoping
        // either. Any logged-in user from ANY business could view another
        // business's cash register (every cash entry — real financial data).
        abort_if($register->business_id !== $this->businessId(), 403);
        $register->load('entries', 'user');
        $expected = $register->calculateExpected();
        return view('cash-registers.show', compact('register', 'expected'));
    }

    public function addEntry(Request $request, CashRegister $register) {
        // Worse than show() — without this, any business could add
        // fraudulent cash entries into another business's register.
        abort_if($register->business_id !== $this->businessId(), 403);
        if (!$register->isOpen()) return back()->with('error', 'Register is closed.');
        $request->validate([
            'entry_type' => 'required|in:sale,refund,expense,float_add,float_remove',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'reference' => 'nullable|string|max:100',
        ]);

        CashRegisterEntry::create([
            'cash_register_id' => $register->id,
            'entry_type' => $request->entry_type,
            'amount' => $request->amount,
            'description' => $request->description,
            'reference' => $request->reference,
        ]);

        return back()->with('success', 'Entry added.');
    }

    public function close(Request $request, CashRegister $register) {
        // Without this, any business could forcibly close another
        // business's active register session mid-shift.
        abort_if($register->business_id !== $this->businessId(), 403);
        if (!$register->isOpen()) return back()->with('error', 'Register is already closed.');
        $request->validate([
            'actual_closing' => 'required|numeric|min:0',
        ]);

        $register->load('entries');
        $expected = $register->calculateExpected();
        $actual = $request->actual_closing;
        $difference = $actual - $expected;

        $register->update([
            'closed_at' => now(),
            'expected_closing' => $expected,
            'actual_closing' => $actual,
            'difference' => $difference,
            'status' => 'closed',
        ]);

        return redirect()->route('cash-registers.index')
            ->with('success', 'Register closed. ' . ($difference >= 0 ? 'Surplus' : 'Shortage') . ': KSh ' . number_format(abs($difference), 2));
    }
}
