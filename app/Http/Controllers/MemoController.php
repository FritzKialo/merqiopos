<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Business;
use App\Models\Memo;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Broadcast announcements to staff — delivered as an AppNotification per
 * recipient (shows up in the bell dropdown everyone already has, see
 * layouts/app.blade.php), not a new inbox/page people have to remember to
 * check. One-way on purpose: an announcement, not a conversation — there's
 * no reply, matching what was actually asked for.
 */
class MemoController extends Controller
{
    private const TARGET_ROLES = ['overall_manager', 'manager', 'cashier'];

    public function index()
    {
        $user     = Auth::user();
        $business = $user->currentBusiness();
        $org      = $user->organization ?? $business?->organization;

        $memos = Memo::where('organization_id', $org?->id)
            ->with(['sender', 'business'])
            ->latest()
            ->paginate(15);

        $multiStore = $org && $org->businesses()->count() > 1;

        return view('settings.memos', [
            'memos'      => $memos,
            'multiStore' => $multiStore,
            'roles'      => self::TARGET_ROLES,
        ]);
    }

    public function store(Request $request)
    {
        $user     = Auth::user();
        $business = $user->currentBusiness();
        $org      = $user->organization ?? $business?->organization;

        if (!$business || !$org) {
            return back()->with('error', 'No business context found.');
        }

        $data = $request->validate([
            'title'       => 'required|string|max:150',
            'message'     => 'required|string|max:1000',
            'scope'       => 'required|in:store,org,role',
            'target_role' => 'nullable|required_if:scope,role|in:' . implode(',', self::TARGET_ROLES),
        ]);

        if ($data['scope'] === 'org' && $org->businesses()->count() <= 1) {
            return back()->with('error', 'Organization-wide memos need more than one store — this org only has one.')->withInput();
        }

        $recipients = $this->resolveRecipients(
            $data['scope'],
            $business,
            $org,
            $data['target_role'] ?? null,
            $user->id
        );

        if ($recipients->isEmpty()) {
            return back()->with('error', 'No staff matched that audience — nothing was sent.')->withInput();
        }

        foreach ($recipients as $r) {
            AppNotification::send(
                $r['business_id'],
                $r['user']->id,
                'memo',
                $data['title'],
                $data['message'],
                null,
                'megaphone'
            );
        }

        Memo::create([
            'organization_id' => $org->id,
            'business_id'     => $business->id,
            'sender_id'       => $user->id,
            'scope'           => $data['scope'],
            'target_role'     => $data['target_role'] ?? null,
            'title'           => $data['title'],
            'message'         => $data['message'],
            'recipient_count' => $recipients->count(),
        ]);

        $count = $recipients->count();
        return redirect()->route('settings.memos.index')
            ->with('success', "Memo sent to {$count} " . Str::plural('person', $count) . '.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{user: User, business_id: int}>
     */
    private function resolveRecipients(
        string $scope,
        Business $business,
        Organization $org,
        ?string $targetRole,
        int $excludeUserId
    ): \Illuminate\Support\Collection {
        $recipients = collect();

        if ($scope === 'role' && $targetRole) {
            $business->users()->wherePivot('role', $targetRole)->get()->each(
                fn ($u) => $recipients->push(['user' => $u, 'business_id' => $business->id])
            );

            return $recipients->unique(fn ($r) => $r['user']->id)
                ->reject(fn ($r) => $r['user']->id === $excludeUserId)
                ->values();
        }

        // 'store' notifies just this business's own staff + the org
        // owner(s); 'org' does the same across every business in the org.
        $businesses = $scope === 'org' ? $org->businesses : collect([$business]);

        foreach ($businesses as $biz) {
            foreach ($biz->users as $staffMember) {
                $recipients->push(['user' => $staffMember, 'business_id' => $biz->id]);
            }
        }

        // Owners aren't linked via the business_user pivot at all (they
        // reach every store through the organization, not a per-store
        // assignment — see User::currentBusiness()), so a memo would
        // otherwise never reach a co-owner just because they have no pivot
        // row anywhere. Notified in the context of the sender's own
        // business, since that's the one they're most likely checking.
        User::where('organization_id', $org->id)->where('role', 'owner')->get()->each(
            fn ($u) => $recipients->push(['user' => $u, 'business_id' => $business->id])
        );

        return $recipients->unique(fn ($r) => $r['user']->id)
            ->reject(fn ($r) => $r['user']->id === $excludeUserId)
            ->values();
    }
}
