<?php
namespace App\Http\Controllers;

use App\Models\StaffProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class P9Controller extends Controller
{
    private function businessId()
    {
        return Auth::user()->currentBusiness()->id;
    }

    public function index(Request $request)
    {
        $year = (int) $request->get('year', now()->year);
        $years = range(now()->year, max(now()->year - 4, 2020));
        $business = Auth::user()->currentBusiness();

        // Find staff who have paid payroll items in the given year
        $paidUserIds = DB::table('payroll_items as pi')
            ->join('payroll_periods as pp', 'pp.id', 'pi.payroll_period_id')
            ->where('pi.business_id', $this->businessId())
            ->where('pi.status', 'paid')
            ->whereYear('pp.period_start', $year)
            ->pluck('pi.user_id');

        $staff = StaffProfile::where('business_id', $this->businessId())
            ->with('user')
            ->whereIn('user_id', $paidUserIds)
            ->get();

        return view('payroll.p9.index', compact('staff', 'year', 'years', 'business'));
    }

    private function fetchItems(int $userId, int $year): \Illuminate\Support\Collection
    {
        return DB::table('payroll_items as pi')
            ->join('payroll_periods as pp', 'pp.id', 'pi.payroll_period_id')
            ->where('pi.user_id', $userId)
            ->where('pi.business_id', $this->businessId())
            ->where('pi.status', 'paid')
            ->whereYear('pp.period_start', $year)
            ->orderBy('pp.period_start')
            ->select('pp.period_start', 'pp.period_end',
                DB::raw("DATE_FORMAT(pp.period_start, '%M %Y') as label"),
                'pi.gross_pay', 'pi.paye', 'pi.nssf_employee',
                'pi.shif_employee as shif', 'pi.net_pay',
                DB::raw('COALESCE(pi.helb, 0) as helb'))
            ->get();
    }

    private function buildTotals(\Illuminate\Support\Collection $items): array
    {
        return [
            'gross_pay'       => $items->sum('gross_pay'),
            'paye'            => $items->sum('paye'),
            'nssf_employee'   => $items->sum('nssf_employee'),
            'shif'            => $items->sum('shif'),
            'helb'            => $items->sum('helb'),
            'net_pay'         => $items->sum('net_pay'),
            'personal_relief' => 2400 * $items->count(),
        ];
    }

    public function show(Request $request, $staffProfileId)
    {
        $year = (int) $request->get('year', now()->year);
        $business = Auth::user()->currentBusiness();
        $staffProfile = StaffProfile::where('business_id', $this->businessId())
            ->with('user')->findOrFail($staffProfileId);

        $items  = $this->fetchItems($staffProfile->user_id, $year);
        $totals = $this->buildTotals($items);

        return view('payroll.p9.show', compact('staffProfile', 'items', 'year', 'totals', 'business'));
    }

    public function pdf(Request $request, $staffProfileId)
    {
        $year = (int) $request->get('year', now()->year);
        $business = Auth::user()->currentBusiness();
        $staffProfile = StaffProfile::where('business_id', $this->businessId())
            ->with('user')->findOrFail($staffProfileId);

        $items  = $this->fetchItems($staffProfile->user_id, $year);
        $totals = $this->buildTotals($items);

        $html = view('payroll.p9.pdf', compact('staffProfile', 'items', 'year', 'totals', 'business'))->render();
        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $staffProfile->user->name);
        $filename = "P9_{$year}_{$safeName}";

        // Use DomPDF if available, otherwise serve HTML
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
                ->setPaper('a4')
                ->download("{$filename}.pdf");
        }

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.html"',
        ]);
    }
}
