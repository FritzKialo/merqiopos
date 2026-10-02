<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DashboardController extends Controller
{
    public function index()
    {
        // ── Organization stats ────────────────────────────────────────────
        // ->real() everywhere below excludes throwaway "Try it now" demo
        // orgs — see Organization::scopeReal().
        $totalOrgs    = Organization::real()->count();
        // There's no permanent Free plan anymore (removed in the 3-tier
        // pricing restructure) — a lapsed org is redirected to the paywall
        // instead of silently downgrading, so every 'active' org here is
        // genuinely paying (or on its Solo trial, counted separately below).
        // The 'free' filters are kept defensively in case any legacy row
        // still carries that value.
        $activeOrgs   = Organization::real()->where('status', 'active')
            ->where('subscription_plan', '!=', 'free')
            ->count();
        $freeOrgs     = Organization::real()->where('status', 'active')
            ->where('subscription_plan', 'free')
            ->count();
        $trialOrgs    = Organization::real()->where('status', 'trial')
            ->where('trial_ends_at', '>=', now())
            ->count();
        $expiredOrgs  = Organization::real()->where(function ($q) {
            $q->where('status', 'suspended')
              ->orWhere(function ($q2) {
                  $q2->where('status', 'trial')
                     ->where('trial_ends_at', '<', now());
              });
        })->count();
        $totalStores  = Business::whereHas('organization', fn ($q) => $q->real())->count();

        // ── Subscription revenue ──────────────────────────────────────────
        $totalRevenue = Subscription::where('status', 'active')->sum('amount');

        $monthRevenue = Subscription::where('status', 'active')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at',  now()->year)
            ->sum('amount');

        $activeSubscriptions = Subscription::where('status', 'active')
            ->where('end_date', '>=', now()->toDateString())
            ->count();

        // ── Revenue chart (last 6 months) ─────────────────────────────────
        $revenueChart = Subscription::where('status', 'active')
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->select(
                DB::raw(\App\Support\PortableSql::yearMonth('created_at')),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')->orderBy('month')
            ->get()
            ->keyBy(fn ($r) => $r->year . '-' . $r->month);

        $monthNames = [
            1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',
            5=>'May',6=>'Jun',7=>'Jul',8=>'Aug',
            9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'
        ];

        $chartLabels  = [];
        $chartRevenue = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $key  = $date->year . '-' . $date->month;
            $chartLabels[]  = $monthNames[$date->month];
            $chartRevenue[] = round($revenueChart[$key]->total ?? 0, 2);
        }

        // ── Plan breakdown ────────────────────────────────────────────────
        $planBreakdown = Organization::real()->whereIn('status', ['active', 'trial'])
            ->select('subscription_plan as plan', DB::raw('count(*) as count'))
            ->groupBy('subscription_plan')
            ->get();

        // ── Recent organizations ──────────────────────────────────────────
        $recentOrgs = Organization::real()->with('owner')
            ->withCount('businesses')
            ->latest()
            ->limit(8)
            ->get();

        // ── Recent payments ───────────────────────────────────────────────
        $recentPayments = Subscription::with('organization.owner')
            ->where('status', 'active')
            ->latest()
            ->limit(8)
            ->get();

        // ── Platform usage (distinct from subscription billing revenue) ───
        // Total value of goods/services actually moving through every
        // store on the platform — a health signal billing revenue alone
        // can't give you: an org can be a paying "Growth" subscriber with
        // zero real trading activity, or a Free-plan org processing
        // thousands of sales. Same sale_status='completed' convention
        // used by every other revenue query in the app.
        $platformGmvMonth = Sale::where('sale_status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_amount');
        $platformGmvAllTime = Sale::where('sale_status', 'completed')->sum('total_amount');

        // ── New signups this week ──────────────────────────────────────────
        $newOrgsThisWeek = Organization::real()->where('created_at', '>=', now()->subDays(7))->count();

        // ── Needs Attention — pulls together the same underlying checks
        // now surfaced individually on the Organizations/Businesses pages,
        // so an admin can see at a glance whether anything needs acting on
        // without visiting each page to look. ────────────────────────────
        // Demo trials expiring is not something an admin needs to act on —
        // PurgeDemoStores already deletes them on schedule — so ->real()
        // here too, or every "Try the demo" click added permanent noise to
        // this list until it self-cleared a day later.
        $trialsEndingSoon = Organization::real()->where('status', 'trial')
            ->whereBetween('trial_ends_at', [now(), now()->addDays(7)])
            ->with('owner')
            ->orderBy('trial_ends_at')
            ->limit(5)
            ->get();

        $mpesaNotConfiguredCount = Business::whereHas('organization', fn ($q) => $q->real())
            ->where(function ($q) {
                foreach (['mpesa_shortcode', 'mpesa_consumer_key', 'mpesa_consumer_secret', 'mpesa_passkey'] as $col) {
                    $q->orWhere(fn ($q2) => $q2->whereNull($col)->orWhere($col, ''));
                }
            })->count();

        $suspendedOrgsCount = Organization::real()->where('status', 'suspended')->count();

        // ── Recent admin activity ─────────────────────────────────────────
        // Every state-changing admin action (suspend/activate, trial
        // extensions, subscription grants/cancels, notes, team-member
        // toggles, admin password changes) now writes an AuditLog row —
        // this is the first place any of that is actually visible. Was
        // previously wired into the model with zero admin-panel usage at
        // all, so "who did what and when" had no answer for anyone.
        $recentAdminActivity = AuditLog::with(['user', 'subject'])
            ->where('event', 'like', 'admin.%')
            ->latest('created_at')
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact(
            'totalOrgs', 'activeOrgs', 'freeOrgs',
            'trialOrgs', 'expiredOrgs',
            'totalStores',
            'totalRevenue', 'monthRevenue',
            'activeSubscriptions',
            'chartLabels', 'chartRevenue',
            'planBreakdown',
            'recentOrgs', 'recentPayments',
            'platformGmvMonth', 'platformGmvAllTime', 'newOrgsThisWeek',
            'trialsEndingSoon', 'mpesaNotConfiguredCount', 'suspendedOrgsCount',
            'recentAdminActivity'
        ));
    }

    public function passwordForm()
    {
        // Every other super admin account on the platform — a security
        // page is exactly where "who else holds this level of access"
        // belongs, and it previously had no visibility into that at all.
        // Excludes the viewer's own row (already shown in the hero above).
        $otherAdmins = \App\Models\User::where('is_super_admin', true)
            ->where('id', '!=', Auth::id())
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'two_factor_enabled', 'two_factor_confirmed_at', 'last_login_at']);

        return view('admin.password', compact('otherAdmins'));
    }

    public function passwordUpdate(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->with('error', 'Current password is incorrect.');
        }

        $user->update(['password' => Hash::make($request->password)]);
        $user->endOtherSessions($request->session()->getId());

        \App\Models\AuditLog::record('admin.password_changed', $user);

        return back()->with('success', 'Password changed successfully.');
    }
}
