<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The owner's view of their own stores' activity: who did what, and what did not
 * work. Scoped to the owner's organization, so nobody sees another business.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $user   = Auth::user();
        $stores = $user->organization
            ? $user->organization->businesses()->orderBy('name')->pluck('name', 'id')
            : collect([$user->currentBusiness()->id => $user->currentBusiness()->name]);

        $q = ActivityLog::whereIn('business_id', $stores->keys())
            ->whereIn('kind', ['request', 'login', 'login_failed'])
            ->with('user:id,name')->latest('id');

        if ($request->filled('business_id') && $stores->has((int) $request->business_id)) $q->where('business_id', $request->business_id);
        if ($request->filled('user'))    $q->whereIn('user_id', User::whereIn('organization_id', [$user->organization_id])->where('name', 'like', '%' . $request->user . '%')->pluck('id'));
        if ($request->filled('outcome')) $request->outcome === 'problems' ? $q->failures() : $q->where('outcome', $request->outcome);
        if ($request->filled('from'))    $q->whereDate('created_at', '>=', $request->from);
        if ($request->filled('to'))      $q->whereDate('created_at', '<=', $request->to);

        $logs = $q->paginate(40)->withQueryString();

        return view('activity.index', compact('logs', 'stores'));
    }
}
