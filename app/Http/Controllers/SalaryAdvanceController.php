<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Business;
use App\Models\SalaryAdvance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class SalaryAdvanceController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    /**
     * Who may view all requests and approve/reject them in this business:
     * owners, overall (org-wide) managers, and the branch manager(s) of the
     * store the advance belongs to.
     */
    private function canManageAdvances(User $user, int $businessId): bool {
        return $user->canActAsOwner()
            || in_array($businessId, $user->managedBusinessIds(), true);
    }

    /**
     * Users who should be notified of a new request: the branch/overall
     * managers assigned to the store (pivot role 'manager') plus the org owner.
     * The requester is excluded so people don't notify themselves.
     */
    private function advanceApprovers(Business $business, int $excludeUserId): Collection {
        $recipients = $business->users()->wherePivot('role', 'manager')->get();

        $owner = $business->organization?->owner;
        if ($owner) {
            $recipients->push($owner);
        }

        return $recipients
            ->reject(fn($u) => $u->id === $excludeUserId)
            ->unique('id')
            ->values();
    }

    // ── List ──────────────────────────────────────────────────────────────
    public function index(Request $request) {
        $user       = Auth::user();
        $businessId = $this->businessId();

        // "mine" = self-service view: always scope to the current user, even for managers.
        $mine       = $request->boolean('mine');
        $canManage  = $this->canManageAdvances($user, $businessId) && !$mine;

        $query = SalaryAdvance::with(['user', 'approver'])
            ->forBusiness($businessId);

        if (!$canManage) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $advances = $query->latest()->paginate(20)->withQueryString();

        return view('staff.advances.index', compact('advances', 'canManage', 'mine'));
    }

    // ── Submit request ────────────────────────────────────────────────────
    public function store(Request $request) {
        $request->validate([
            'amount' => 'required|numeric|min:1|max:999999',
            'reason' => 'nullable|string|max:500',
        ]);

        $business = Auth::user()->currentBusiness();

        $advance = SalaryAdvance::create([
            'business_id' => $business->id,
            'user_id'     => Auth::id(),
            'amount'      => $request->amount,
            'reason'      => $request->reason,
            'status'      => 'pending',
        ]);

        // Notify everyone who can approve.
        $detail = 'KSh ' . number_format($advance->amount, 0)
            . ($advance->reason ? " — {$advance->reason}" : '');
        foreach ($this->advanceApprovers($business, Auth::id()) as $approver) {
            AppNotification::send(
                $business->id,
                $approver->id,
                'advance_requested',
                'New Salary Advance Request',
                Auth::user()->name . " requested an advance of {$detail}.",
                route('staff.advances.index'),
                'dollar-sign'
            );
        }

        return redirect()->route('staff.advances.index')->with('success', 'Salary advance request submitted.');
    }

    // ── Approve ───────────────────────────────────────────────────────────
    public function approve(SalaryAdvance $advance) {
        abort_if(!$this->canManageAdvances(Auth::user(), $this->businessId()), 403);
        abort_if($advance->business_id !== $this->businessId(), 403);
        if ($advance->user_id === Auth::id() && !Auth::user()->hasRole('owner')) return back()->with('error', 'You cannot approve or reject your own request — ask another manager or the owner.');
        abort_if(!$advance->isPending(), 422);

        $advance->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        AppNotification::send(
            $advance->business_id,
            $advance->user_id,
            'advance_approved',
            'Salary Advance Approved',
            'Your advance request of KSh ' . number_format($advance->amount, 0)
                . ' was approved by ' . Auth::user()->name . '.',
            route('staff.advances.index'),
            'check-circle'
        );

        return back()->with('success', 'Salary advance approved.');
    }

    // ── Reject ────────────────────────────────────────────────────────────
    public function reject(SalaryAdvance $advance) {
        abort_if(!$this->canManageAdvances(Auth::user(), $this->businessId()), 403);
        abort_if($advance->business_id !== $this->businessId(), 403);
        if ($advance->user_id === Auth::id() && !Auth::user()->hasRole('owner')) return back()->with('error', 'You cannot approve or reject your own request — ask another manager or the owner.');
        abort_if(!$advance->isPending(), 422);

        $advance->update(['status' => 'rejected']);

        AppNotification::send(
            $advance->business_id,
            $advance->user_id,
            'advance_rejected',
            'Salary Advance Rejected',
            'Your advance request of KSh ' . number_format($advance->amount, 0)
                . ' was rejected.',
            route('staff.advances.index'),
            'x-circle'
        );

        return back()->with('success', 'Salary advance rejected.');
    }

    // ── Delete ────────────────────────────────────────────────────────────
    public function destroy(SalaryAdvance $advance) {
        abort_if($advance->user_id !== Auth::id(), 403);
        abort_if(!$advance->isPending(), 422);

        $advance->delete();

        return redirect()->route('staff.advances.index')->with('success', 'Advance request deleted.');
    }
}
