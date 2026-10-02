<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $businessId = Auth::user()->currentBusiness()->id;

        $query = AuditLog::where('business_id', $businessId)->with('user')->latest('created_at');

        if ($request->action) $query->where(function($q) use ($request) {
            $q->where('action', $request->action)->orWhere('event', $request->action);
        });
        if ($request->model) $query->where(function($q) use ($request) {
            $q->where('model_type', $request->model)->orWhere('subject_type', 'like', '%'.$request->model.'%');
        });
        if ($request->user_id) $query->where('user_id', $request->user_id);
        if ($request->date_from) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->date_to) $query->whereDate('created_at', '<=', $request->date_to);

        if ($request->get('export') === 'csv') {
            $all = (clone $query)->limit(5000)->get();
            return response()->stream(function () use ($all) {
                $out = fopen('php://output', 'w');
                \App\Support\Csv::put($out, ['Date', 'User', 'Action/Event', 'Description', 'Model', 'IP']);
                foreach ($all as $log) {
                    \App\Support\Csv::put($out, [
                        $log->created_at->format('d/m/Y H:i'),
                        $log->user?->name ?? 'System',
                        $log->action ?? $log->event,
                        $log->summary(),
                        ($log->model_type ?? $log->subject_type ?? '') . ($log->model_id || $log->subject_id ? ' #' . ($log->model_id ?? $log->subject_id) : ''),
                        $log->ip_address ?? '',
                    ]);
                }
                fclose($out);
            }, 200, [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => 'attachment; filename="audit_log.csv"',
            ]);
        }

        $logs = $query->paginate(50)->withQueryString();

        $users = \App\Models\User::whereHas('businesses', fn($q) => $q->where('businesses.id', $businessId))->get();

        $actions = AuditLog::where('business_id', $businessId)
            ->selectRaw('COALESCE(action, event) as act')
            ->distinct()
            ->pluck('act')
            ->filter()
            ->values();

        $models = AuditLog::where('business_id', $businessId)
            ->selectRaw('COALESCE(model_type, subject_type) as model_name')
            ->distinct()
            ->pluck('model_name')
            ->filter()
            ->map(fn($m) => class_basename($m))
            ->unique()
            ->values();

        return view('audit-log.index', compact('logs', 'users', 'actions', 'models'));
    }

    public function export(Request $request)
    {
        return $this->index($request->merge(['export' => 'csv']));
    }
}
