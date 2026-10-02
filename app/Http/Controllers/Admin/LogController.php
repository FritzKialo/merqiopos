<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Business;
use App\Models\ErrorLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Developer logs (super admin only): what stores do, and what fails.
 * Everything is read from error_logs / activity_logs (see App\Support\DevLog)
 * plus the failure records other parts of the app already keep.
 */
class LogController extends Controller
{
    // ── Health overview ──────────────────────────────────────────────────────────
    public function index()
    {
        $since24 = now()->subDay();
        $since7  = now()->subDays(7);

        $stats = [
            'errors_24h'     => (int) ErrorLog::where('last_seen_at', '>=', $since24)->sum('count'),
            'error_groups'   => ErrorLog::where('last_seen_at', '>=', $since24)->distinct('fingerprint')->count('fingerprint'),
            'open_groups'    => ErrorLog::whereNull('resolved_at')->distinct('fingerprint')->count('fingerprint'),
            'failed_24h'     => ActivityLog::failures()->where('created_at', '>=', $since24)->count(),
            'denied_24h'     => ActivityLog::where('outcome', 'denied')->where('created_at', '>=', $since24)->count(),
            'bad_logins_24h' => ActivityLog::where('kind', 'login_failed')->where('created_at', '>=', $since24)->count(),
            'active_stores'  => ActivityLog::where('created_at', '>=', $since24)->whereNotNull('business_id')->distinct('business_id')->count('business_id'),
            'actions_24h'    => ActivityLog::where('kind', 'request')->where('created_at', '>=', $since24)->count(),
        ];

        // Stores ranked by trouble (last 7 days) — with their last activity.
        $troubled = ActivityLog::selectRaw("business_id, SUM(outcome IN ('failed','validation','denied','error')) as bad, COUNT(*) as total, MAX(created_at) as last_seen")
            ->where('created_at', '>=', $since7)->whereNotNull('business_id')
            ->groupBy('business_id')->orderByDesc('bad')->limit(10)->get();
        $storeNames = Business::withoutGlobalScopes()->whereIn('id', $troubled->pluck('business_id'))->pluck('name', 'id');

        $failingRoutes = ActivityLog::selectRaw("route_name, COUNT(*) as bad, COUNT(DISTINCT business_id) as stores")
            ->whereIn('outcome', ['failed', 'validation', 'error'])->where('created_at', '>=', $since7)->whereNotNull('route_name')
            ->groupBy('route_name')->orderByDesc('bad')->limit(8)->get();

        $slowRoutes = ActivityLog::selectRaw('route_name, ROUND(AVG(duration_ms)) as avg_ms, MAX(duration_ms) as max_ms, COUNT(*) as hits')
            ->where('created_at', '>=', $since7)->whereNotNull('route_name')->whereNotNull('duration_ms')
            ->groupBy('route_name')->having('hits', '>=', 3)->orderByDesc('avg_ms')->limit(8)->get();

        return view('admin.logs.index', compact('stats', 'troubled', 'storeNames', 'failingRoutes', 'slowRoutes'));
    }

    // ── Errors, one line per bug ─────────────────────────────────────────────────
    public function errors(Request $request)
    {
        $q = ErrorLog::query();
        if ($request->filled('business_id')) $q->where('business_id', $request->business_id);
        if ($request->filled('from'))        $q->whereDate('last_seen_at', '>=', $request->from);
        if ($request->filled('to'))          $q->whereDate('last_seen_at', '<=', $request->to);
        if ($request->filled('q'))           $q->where(function ($w) use ($request) {
            $w->where('message', 'like', '%' . $request->q . '%')->orWhere('exception', 'like', '%' . $request->q . '%')->orWhere('path', 'like', '%' . $request->q . '%');
        });

        $groups = $q->selectRaw('fingerprint, MAX(id) as last_id, SUM(count) as total, COUNT(DISTINCT business_id) as stores, MIN(first_seen_at) as first_seen, MAX(last_seen_at) as last_seen, SUM(resolved_at IS NULL) as open_rows')
            ->groupBy('fingerprint');

        $status = $request->get('status', 'open');
        if ($status === 'open')     $groups->having('open_rows', '>', 0);
        if ($status === 'resolved') $groups->having('open_rows', '=', 0);

        $groups = $groups->orderByDesc('last_seen')->paginate(25)->withQueryString();
        $samples = ErrorLog::whereIn('id', $groups->pluck('last_id'))->get()->keyBy('id');
        $stores  = Business::withoutGlobalScopes()->orderBy('name')->pluck('name', 'id');

        return view('admin.logs.errors', compact('groups', 'samples', 'stores', 'status'));
    }

    public function errorShow(string $fingerprint)
    {
        $rows = ErrorLog::where('fingerprint', $fingerprint)->orderByDesc('last_seen_at')->get();
        abort_if($rows->isEmpty(), 404);

        $sample   = $rows->first();
        $byStore  = $rows->groupBy('business_id')->map(fn ($g) => [
            'count' => $g->sum('count'), 'last' => $g->max('last_seen_at'), 'users' => $g->pluck('user_id')->filter()->unique()->count(),
        ]);
        $storeNames = Business::withoutGlobalScopes()->whereIn('id', $rows->pluck('business_id')->filter())->pluck('name', 'id');
        $open = $rows->whereNull('resolved_at')->isNotEmpty();

        return view('admin.logs.error-show', compact('sample', 'rows', 'byStore', 'storeNames', 'open', 'fingerprint'));
    }

    public function resolve(string $fingerprint)
    {
        ErrorLog::where('fingerprint', $fingerprint)->whereNull('resolved_at')->update(['resolved_at' => now()]);

        return redirect()->route('admin.logs.errors')->with('success', 'Marked resolved. If the same error happens again it reappears in the list.');
    }

    // ── Activity timeline ────────────────────────────────────────────────────────
    public function activity(Request $request)
    {
        $q = ActivityLog::query()->with('user:id,name,email')->latest('id');

        if ($request->filled('business_id')) $q->where('business_id', $request->business_id);
        if ($request->filled('user'))        $q->whereIn('user_id', \App\Models\User::where('name', 'like', '%' . $request->user . '%')->orWhere('email', 'like', '%' . $request->user . '%')->pluck('id'));
        if ($request->filled('outcome'))     $request->outcome === 'problems' ? $q->failures() : $q->where('outcome', $request->outcome);
        if ($request->filled('kind'))        $q->where('kind', $request->kind);
        if ($request->filled('route'))       $q->where('route_name', 'like', '%' . $request->route . '%');
        if ($request->filled('from'))        $q->whereDate('created_at', '>=', $request->from);
        if ($request->filled('to'))          $q->whereDate('created_at', '<=', $request->to);

        $logs   = $q->paginate(50)->withQueryString();
        $stores = Business::withoutGlobalScopes()->orderBy('name')->pluck('name', 'id');

        return view('admin.logs.activity', compact('logs', 'stores'));
    }

    // ── Everything that failed, from every source ────────────────────────────────
    public function failures(Request $request)
    {
        $since = now()->subDays((int) $request->get('days', 7));
        $items = collect();
        $names = Business::withoutGlobalScopes()->pluck('name', 'id');

        foreach (ErrorLog::where('last_seen_at', '>=', $since)->orderByDesc('last_seen_at')->limit(150)->get() as $e) {
            $items->push(['when' => $e->last_seen_at, 'source' => 'Error', 'store' => $names[$e->business_id] ?? null,
                'text' => class_basename($e->exception) . ': ' . $e->message . ($e->count > 1 ? " (×{$e->count})" : ''),
                'link' => route('admin.logs.errors.show', $e->fingerprint)]);
        }
        foreach (ActivityLog::failures()->where('created_at', '>=', $since)->latest('id')->limit(200)->get() as $a) {
            $items->push(['when' => $a->created_at, 'source' => ucfirst($a->outcome === 'validation' ? 'Rejected input' : ($a->kind === 'login_failed' ? 'Failed sign-in' : $a->outcome)),
                'store' => $names[$a->business_id] ?? null,
                'text' => trim(($a->method ? $a->method . ' ' : '') . ($a->route_name ?: $a->path) . ' — ' . $a->message),
                'link' => route('admin.logs.activity', ['route' => $a->route_name, 'business_id' => $a->business_id])]);
        }
        if (Schema::hasTable('failed_jobs')) {
            foreach (DB::table('failed_jobs')->where('failed_at', '>=', $since)->orderByDesc('failed_at')->limit(50)->get() as $j) {
                $name = json_decode($j->payload, true)['displayName'] ?? 'Background job';
                $items->push(['when' => \Illuminate\Support\Carbon::parse($j->failed_at), 'source' => 'Background job', 'store' => null,
                    'text' => class_basename($name) . ': ' . mb_substr(strtok((string) $j->exception, "\n"), 0, 200), 'link' => null]);
            }
        }
        foreach (DB::table('sales')->where('etims_status', 'failed')->where('updated_at', '>=', $since)->orderByDesc('updated_at')->limit(50)->get(['id', 'business_id', 'invoice_number', 'updated_at']) as $s) {
            $items->push(['when' => \Illuminate\Support\Carbon::parse($s->updated_at), 'source' => 'KRA eTIMS', 'store' => $names[$s->business_id] ?? null,
                'text' => "Sale {$s->invoice_number} was not accepted by KRA", 'link' => null]);
        }
        foreach (DB::table('mpesa_transactions')->where('status', 'FAILED')->where('updated_at', '>=', $since)->orderByDesc('updated_at')->limit(50)->get(['business_id', 'type', 'amount', 'updated_at']) as $m) {
            $items->push(['when' => \Illuminate\Support\Carbon::parse($m->updated_at), 'source' => 'M-Pesa', 'store' => $names[$m->business_id] ?? null,
                'text' => ucfirst((string) $m->type) . ' payment of KES ' . number_format((float) $m->amount, 2) . ' failed', 'link' => null]);
        }
        foreach (DB::table('campaign_recipients as r')->join('campaigns as c', 'c.id', '=', 'r.campaign_id')->where('r.status', 'failed')->where('r.updated_at', '>=', $since)
            ->orderByDesc('r.updated_at')->limit(50)->get(['c.business_id', 'c.name', 'c.channel', 'r.error_message', 'r.updated_at']) as $c) {
            $items->push(['when' => \Illuminate\Support\Carbon::parse($c->updated_at), 'source' => 'Campaign message', 'store' => $names[$c->business_id] ?? null,
                'text' => "\"{$c->name}\" ({$c->channel}): " . mb_substr((string) $c->error_message, 0, 160), 'link' => null]);
        }

        $items = $items->sortByDesc('when')->values();
        $counts = $items->countBy('source')->sortDesc();
        if ($request->filled('source')) $items = $items->where('source', $request->source)->values();

        return view('admin.logs.failures', ['items' => $items->take(300), 'counts' => $counts, 'days' => (int) $request->get('days', 7)]);
    }
}
