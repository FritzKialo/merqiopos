<?php

namespace App\Http\Controllers;

use App\Jobs\SendMpesaPayment;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\PayrollCalculationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    public function __construct(
        private PayrollCalculationService $calculator
    ) {}

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function business()
    {
        return Auth::user()->currentBusiness();
    }

    private function authorizeWritePeriod(PayrollPeriod $period): void
    {
        if ($period->business_id !== $this->business()->id) {
            abort(403);
        }
    }

    // ── Index — list periods ──────────────────────────────────────────────────

    public function index(Request $request)
    {
        $business = $this->business();

        $periods = PayrollPeriod::forBusiness($business->id)
            ->latest('period_start')
            ->paginate(15)
            ->withQueryString();

        $pendingDraft = PayrollPeriod::forBusiness($business->id)
            ->where('status', 'draft')
            ->count();

        return view('payroll.index', compact('periods', 'business', 'pendingDraft'));
    }

    // ── Create / store new period ─────────────────────────────────────────────

    public function create()
    {
        $business  = $this->business();
        $payCycle  = $business->payCycle();

        // Suggest next period dates based on pay cycle
        $lastPeriod = PayrollPeriod::forBusiness($business->id)
            ->latest('period_end')
            ->first();

        $suggestedStart = $lastPeriod
            ? $lastPeriod->period_end->addDay()
            : now()->startOfMonth();

        $suggestedEnd = match ($payCycle) {
            'weekly'    => $suggestedStart->copy()->addDays(6),
            'bi_weekly' => $suggestedStart->copy()->addDays(13),
            default     => $suggestedStart->copy()->endOfMonth(),
        };

        return view('payroll.create', compact(
            'business', 'payCycle',
            'suggestedStart', 'suggestedEnd'
        ));
    }

    public function store(Request $request)
    {
        $business = $this->business();

        $request->validate([
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
            'pay_cycle'    => 'required|in:weekly,bi_weekly,monthly',
            'notes'        => 'nullable|string|max:500',
        ]);

        // Prevent overlapping periods for the same business
        $overlap = PayrollPeriod::forBusiness($business->id)
            ->where('period_start', '<=', $request->period_end)
            ->where('period_end',   '>=', $request->period_start)
            ->exists();

        if ($overlap) {
            return back()
                ->withInput()
                ->withErrors(['period_start' => 'A payroll period already exists that overlaps these dates.']);
        }

        $period = PayrollPeriod::create([
            'business_id'  => $business->id,
            'period_start' => $request->period_start,
            'period_end'   => $request->period_end,
            'pay_cycle'    => $request->pay_cycle,
            'notes'        => $request->notes,
            'status'       => 'draft',
        ]);

        return redirect()
            ->route('payroll.show', $period)
            ->with('success', 'Payroll period created. Review and calculate below.');
    }

    // ── Show period detail ────────────────────────────────────────────────────

    public function show(PayrollPeriod $payrollPeriod)
    {
        $this->authorizeWritePeriod($payrollPeriod);

        $payrollPeriod->load(['items.user.staffProfiles', 'business']);

        $business      = $payrollPeriod->business;
        $staffProfiles = $business->staffProfiles()
            ->with('user')
            ->where(function ($q) use ($payrollPeriod) {
                $q->whereNull('termination_date')
                  ->orWhereDate('termination_date', '>=', $payrollPeriod->period_start);
            })
            ->get();

        return view('payroll.show', compact('payrollPeriod', 'business', 'staffProfiles'));
    }

    // ── Calculate (or recalculate) all items for a period ────────────────────

    public function calculate(PayrollPeriod $payrollPeriod)
    {
        $this->authorizeWritePeriod($payrollPeriod);

        if ($payrollPeriod->isApproved() || $payrollPeriod->isPaid()) {
            return back()->with('error', 'Cannot recalculate an approved or paid period.');
        }

        try {
            DB::beginTransaction();

            $items = $this->calculator->calculatePeriod($payrollPeriod);

            DB::commit();

            return back()->with('success', count($items) . ' payroll item(s) calculated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Calculation failed: ' . $e->getMessage());
        }
    }

    // ── Approve period ────────────────────────────────────────────────────────

    public function approve(PayrollPeriod $payrollPeriod)
    {
        $this->authorizeWritePeriod($payrollPeriod);

        if (!$payrollPeriod->isDraft()) {
            return back()->with('error', 'Only draft periods can be approved.');
        }

        if ($payrollPeriod->items()->count() === 0) {
            return back()->with('error', 'Calculate payroll items before approving.');
        }

        $payrollPeriod->update([
            'status'      => 'approved',
            'approved_at' => now(),
        ]);

        \App\Models\AuditLog::record('payroll.approved', $payrollPeriod, [
            'period' => $payrollPeriod->period_start->toDateString() . ' to ' . $payrollPeriod->period_end->toDateString(),
            'gross'  => (float) $payrollPeriod->total_gross,
            'net'    => (float) $payrollPeriod->total_net,
        ]);

        return back()->with('success', 'Payroll period approved.');
    }

    // ── Mark ALL remaining items as paid ─────────────────────────────────────

    public function markPaid(PayrollPeriod $payrollPeriod)
    {
        $this->authorizeWritePeriod($payrollPeriod);

        if (! $payrollPeriod->isApproved()) {
            return back()->with('error', 'Only approved periods can be marked as paid.');
        }

        $pending = $payrollPeriod->items()->where('status', 'pending')->count();
        if ($pending === 0) {
            return back()->with('info', 'All items are already paid.');
        }

        try {
            DB::beginTransaction();

            $payrollPeriod->items()
                ->where('status', 'pending')
                ->update(['status' => 'paid']);

            $payrollPeriod->update([
                'status'  => 'paid',
                'paid_at' => now(),
            ]);

            $this->postPayrollExpense($payrollPeriod);

            DB::commit();

            \App\Models\AuditLog::record('payroll.paid', $payrollPeriod, ['employees' => $pending, 'net' => (float) $payrollPeriod->total_net]);

            return back()->with('success', "{$pending} employee(s) marked as paid and expense posted.");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to mark as paid: ' . $e->getMessage());
        }
    }

    // ── Mark a single item as paid ────────────────────────────────────────────

    public function markItemPaid(PayrollPeriod $payrollPeriod, PayrollItem $payrollItem)
    {
        $this->authorizeWritePeriod($payrollPeriod);

        if ($payrollItem->payroll_period_id !== $payrollPeriod->id) {
            abort(404);
        }

        if (! $payrollPeriod->isApproved()) {
            return back()->with('error', 'Period must be approved before paying individual employees.');
        }

        if ($payrollItem->isPaid()) {
            return back()->with('info', $payrollItem->user->name . ' is already paid.');
        }

        $business = $this->business();
        $employee = $payrollItem->user;

        // Use M-Pesa B2C when the business has full credentials and the employee has a phone.
        // The job itself handles dry-run vs live; status pending_payment → paid happens either
        // via the Safaricom B2C callback (production) or inside the job (dry-run/non-production).
        // A payout also needs the Daraja initiator (name + security credential):
        // without them the request can never be authorised, so the item is
        // handled as a manual payment instead of being sent and failing.
        $payPhone = $employee?->staffProfileFor($business->id)?->mpesa_phone;
        $hasMpesa = $employee
            && ! empty($payPhone)
            && $business->hasMpesaConfigured()
            && ! empty($business->mpesa_initiator_name)
            && ! empty($business->mpesa_security_credential);

        try {
            DB::beginTransaction();

            if ($hasMpesa) {
                $payrollItem->update(['status' => 'pending_payment']);

                SendMpesaPayment::dispatch(
                    payrollItemId:  $payrollItem->id,
                    amount:         (float) $payrollItem->net_pay,
                    phoneNumber:    $payPhone,
                    shortcode:      $business->mpesa_shortcode,
                    consumerKey:    $business->mpesa_consumer_key ?? '',
                    consumerSecret: $business->mpesa_consumer_secret ?? '',
                    environment:    $business->mpesa_environment ?? 'sandbox',
                    reference:      'PAYROLL-' . $payrollItem->id,
                    dryRun:         false,
                );

                DB::commit();

                return back()->with('success',
                    $employee->name . ' — M-Pesa payment dispatched (KSh '
                    . number_format($payrollItem->net_pay, 0)
                    . '). Status will update when Safaricom confirms.'
                );
            }

            // No M-Pesa configured: mark paid immediately and post expense if period closes.
            $payrollItem->update(['status' => 'paid']);

            $closed = $payrollPeriod->checkAndClose();
            if ($closed) {
                $payrollPeriod->refresh();
                $this->postPayrollExpense($payrollPeriod);
            }

            DB::commit();

            $msg = $employee->name . ' marked as paid (KSh ' . number_format($payrollItem->net_pay, 0) . ').';
            if ($closed) {
                $msg .= ' All employees paid — period closed and expense posted.';
            }

            return back()->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed: ' . $e->getMessage());
        }
    }

    // ── Delete draft period ───────────────────────────────────────────────────

    public function destroy(PayrollPeriod $payrollPeriod)
    {
        $this->authorizeWritePeriod($payrollPeriod);

        if (!$payrollPeriod->isDraft()) {
            return back()->with('error', 'Only draft periods can be deleted.');
        }

        // Draft calculation marks salary advances as 'deducted'. Deleting the
        // period without releasing them meant the employee's advance was never
        // recovered from any payslip.
        \App\Models\SalaryAdvance::where('deduction_period_id', $payrollPeriod->id)
            ->where('status', 'deducted')
            ->update(['status' => 'approved', 'deducted_at' => null, 'deduction_period_id' => null]);

        \App\Models\AuditLog::record('payroll.deleted', $payrollPeriod, [
            'period' => $payrollPeriod->period_start->toDateString() . ' to ' . $payrollPeriod->period_end->toDateString(),
        ]);

        $payrollPeriod->delete();

        return redirect()
            ->route('payroll.index')
            ->with('success', 'Payroll period deleted.');
    }

    // ── Payslip for a single item ─────────────────────────────────────────────

    // List the current user's own payslips (self-service, any role).
    public function myPayslips()
    {
        $payslips = PayrollItem::where('user_id', Auth::id())
            ->where('business_id', $this->business()->id)
            ->with('period')
            ->latest()
            ->get();

        return view('payroll.my-payslips', compact('payslips'));
    }

    public function payslip(PayrollPeriod $payrollPeriod, PayrollItem $payrollItem)
    {
        $this->authorizeWritePeriod($payrollPeriod);

        if ($payrollItem->payroll_period_id !== $payrollPeriod->id) {
            abort(404);
        }

        // Non-managers may only view their OWN payslip (salary privacy).
        $user = Auth::user();
        if (!$user->canActAsOwner() && !$user->isManager() && $payrollItem->user_id !== $user->id) {
            abort(403);
        }

        $payrollItem->load('user');
        $breakdown = $this->calculator->payeBandBreakdown(
            (float) $payrollItem->deduction_details['taxable_income'] ?? 0
        );

        return view('payroll.payslip', compact(
            'payrollPeriod', 'payrollItem', 'breakdown'
        ));
    }

    // ── P9 annual tax certificate ─────────────────────────────────────────────

    public function p9(User $user, int $year)
    {
        $business = $this->business();

        // Confirm the employee belongs to the current store
        if (!$user->businesses()->where('business_id', $business->id)->exists()) {
            abort(403);
        }

        // Valid tax years: 2020 to current year
        $currentYear = (int) now()->format('Y');
        if ($year < 2020 || $year > $currentYear) {
            abort(404, 'Invalid tax year.');
        }

        // Fetch all paid payroll items for this employee in the given calendar year
        $items = PayrollItem::where('user_id', $user->id)
            ->whereHas('period', function ($q) use ($business, $year) {
                $q->where('business_id', $business->id)
                  ->where('status', 'paid')
                  ->whereYear('period_start', $year);
            })
            ->with('period')
            ->orderBy('created_at')
            ->get();

        // Build month-indexed summary (key = 1–12)
        $months = [];
        foreach ($items as $item) {
            $month = (int) $item->period->period_start->format('n');
            if (!isset($months[$month])) {
                $months[$month] = [
                    'gross'        => 0,
                    'nssf_ee'      => 0,
                    'taxable'      => 0,
                    'paye'         => 0,
                    'net'          => 0,
                ];
            }
            $months[$month]['gross']   += $item->gross_pay;
            $months[$month]['nssf_ee'] += $item->nssf_employee;
            $months[$month]['taxable'] += $item->deduction_details['taxable_income'] ?? 0;
            $months[$month]['paye']    += $item->paye;
            $months[$month]['net']     += $item->net_pay;
        }

        // Annual totals
        $totals = [
            'gross'   => collect($months)->sum('gross'),
            'nssf_ee' => collect($months)->sum('nssf_ee'),
            'taxable' => collect($months)->sum('taxable'),
            'paye'    => collect($months)->sum('paye'),
            'net'     => collect($months)->sum('net'),
        ];

        $profile     = $user->staffProfileFor($business->id);
        $employerPin = $business->payroll_settings['employer_pin'] ?? null;

        $pdf = Pdf::loadView('payroll.p9', compact(
            'user', 'profile', 'business', 'year',
            'months', 'totals', 'employerPin'
        ))->setPaper('a4', 'portrait');

        $filename = 'P9_' . $year . '_' . preg_replace('/\s+/', '_', $user->name) . '.pdf';

        return $pdf->download($filename);
    }

    // ── Internal: post payroll as business expense ────────────────────────────

    private function postPayrollExpense(PayrollPeriod $period): void
    {
        $period->postExpenseOnce(Auth::id());
    }

    // ── PAYE / NSSF / SHIF Remittance ────────────────────────────────────────

    public function payeRemittance(Request $request)
    {
        $businessId = \Illuminate\Support\Facades\Auth::user()->currentBusiness()->id;
        $month = (int) $request->get('month', now()->month);
        $year  = (int) $request->get('year',  now()->year);
        $period = \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y');

        $items = \Illuminate\Support\Facades\DB::table('payroll_items as pi')
            ->join('staff_profiles as sp', function ($j) {
                $j->on('sp.user_id', 'pi.user_id')->on('sp.business_id', 'pi.business_id');
            })
            ->join('users as u', 'u.id', 'sp.user_id')
            ->join('payroll_periods as pp', 'pp.id', 'pi.payroll_period_id')
            ->where('pi.business_id', $businessId)
            ->where('pi.status', 'paid')
            ->whereMonth('pp.period_start', $month)
            ->whereYear('pp.period_start', $year)
            ->select('u.name', 'sp.kra_pin', 'pi.gross_pay', 'pi.paye')
            ->get();

        $total = $items->sum('paye');
        if ($request->get('download') === 'csv') {
            return $this->downloadRemittanceCsv($items, $total, $period, 'PAYE', ['Employee','KRA PIN','Gross Pay','PAYE'], fn($i) => [$i->name, $i->kra_pin ?? 'N/A', number_format($i->gross_pay,2), number_format($i->paye,2)], "PAYE_{$year}_{$month}.csv");
        }
        return view('payroll.remittance', compact('items','total','period','month','year') + ['type'=>'PAYE','columns'=>['Employee','KRA PIN','Gross Pay','PAYE'],'fields'=>['name','kra_pin','gross_pay','paye'],'note'=>'Submit to KRA via iTax by the 9th of the following month.','route'=>'payroll.paye-remittance']);
    }

    // Affordable Housing Levy: 1.5% employee + 1.5% employer, remitted to KRA.
    public function housingLevyRemittance(Request $request)
    {
        $businessId = \Illuminate\Support\Facades\Auth::user()->currentBusiness()->id;
        $month = (int) $request->get('month', now()->month);
        $year  = (int) $request->get('year',  now()->year);
        $period = \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y');

        $items = \Illuminate\Support\Facades\DB::table('payroll_items as pi')
            ->join('staff_profiles as sp', function ($j) {
                $j->on('sp.user_id', 'pi.user_id')->on('sp.business_id', 'pi.business_id');
            })
            ->join('users as u', 'u.id', 'sp.user_id')
            ->join('payroll_periods as pp', 'pp.id', 'pi.payroll_period_id')
            ->where('pi.business_id', $businessId)
            ->where('pi.status', 'paid')
            ->where('pi.housing_levy_employee', '>', 0)
            ->whereMonth('pp.period_start', $month)
            ->whereYear('pp.period_start', $year)
            ->select('u.name', 'sp.kra_pin', 'pi.gross_pay', 'pi.housing_levy_employee', 'pi.housing_levy_employer',
                \Illuminate\Support\Facades\DB::raw('pi.housing_levy_employee + pi.housing_levy_employer as total_levy'))
            ->get();

        $total = $items->sum('total_levy');
        $columns = ['Employee', 'KRA PIN', 'Gross Pay', 'Employee Levy (1.5%)', 'Employer Levy (1.5%)', 'Total'];
        if ($request->get('download') === 'csv') {
            return $this->downloadRemittanceCsv($items, $total, $period, 'Housing Levy', $columns,
                fn($i) => [$i->name, $i->kra_pin ?? 'N/A', number_format($i->gross_pay, 2), number_format($i->housing_levy_employee, 2), number_format($i->housing_levy_employer, 2), number_format($i->total_levy, 2)],
                "HousingLevy_{$year}_{$month}.csv");
        }
        return view('payroll.remittance', compact('items', 'total', 'period', 'month', 'year') + [
            'type' => 'Housing Levy', 'columns' => $columns,
            'fields' => ['name', 'kra_pin', 'gross_pay', 'housing_levy_employee', 'housing_levy_employer', 'total_levy'],
            'note' => 'Affordable Housing Levy — remit to KRA via iTax by the 9th of the following month.',
            'route' => 'payroll.housing-levy-remittance',
        ]);
    }

    public function nssfRemittance(Request $request)
    {
        $businessId = \Illuminate\Support\Facades\Auth::user()->currentBusiness()->id;
        $month = (int) $request->get('month', now()->month);
        $year  = (int) $request->get('year',  now()->year);
        $period = \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y');

        $items = \Illuminate\Support\Facades\DB::table('payroll_items as pi')
            ->join('staff_profiles as sp', function ($j) {
                $j->on('sp.user_id', 'pi.user_id')->on('sp.business_id', 'pi.business_id');
            })
            ->join('users as u', 'u.id', 'sp.user_id')
            ->join('payroll_periods as pp', 'pp.id', 'pi.payroll_period_id')
            ->where('pi.business_id', $businessId)
            ->where('pi.status', 'paid')
            ->whereMonth('pp.period_start', $month)
            ->whereYear('pp.period_start', $year)
            ->select('u.name', 'sp.nssf_no as nssf_number', 'pi.nssf_employee', 'pi.nssf_employer',
                \Illuminate\Support\Facades\DB::raw('pi.nssf_employee + pi.nssf_employer as total_nssf'))
            ->get();

        $total = $items->sum('total_nssf');
        if ($request->get('download') === 'csv') {
            return $this->downloadRemittanceCsv($items, $total, $period, 'NSSF', ['Employee','NSSF No.','Employee Contrib.','Employer Contrib.','Total'], fn($i) => [$i->name,$i->nssf_number??'N/A',number_format($i->nssf_employee,2),number_format($i->nssf_employer??0,2),number_format($i->total_nssf,2)], "NSSF_{$year}_{$month}.csv");
        }
        // The on-screen columns previously said "Employee" twice (once for
        // the name column, once for the NSSF employee-contribution column,
        // which was actually mislabeled) — the CSV export already used the
        // correct, distinct "Employee Contrib./Employer Contrib." labels;
        // matched the on-screen table to it.
        return view('payroll.remittance', compact('items','total','period','month','year') + ['type'=>'NSSF','columns'=>['Employee','NSSF No.','Employee Contrib.','Employer Contrib.','Total'],'fields'=>['name','nssf_number','nssf_employee','nssf_employer','total_nssf'],'note'=>'Submit to NSSF by the 9th of the following month.','route'=>'payroll.nssf-remittance']);
    }

    public function shifRemittance(Request $request)
    {
        $businessId = \Illuminate\Support\Facades\Auth::user()->currentBusiness()->id;
        $month = (int) $request->get('month', now()->month);
        $year  = (int) $request->get('year',  now()->year);
        $period = \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y');

        $items = \Illuminate\Support\Facades\DB::table('payroll_items as pi')
            ->join('staff_profiles as sp', function ($j) {
                $j->on('sp.user_id', 'pi.user_id')->on('sp.business_id', 'pi.business_id');
            })
            ->join('users as u', 'u.id', 'sp.user_id')
            ->join('payroll_periods as pp', 'pp.id', 'pi.payroll_period_id')
            ->where('pi.business_id', $businessId)
            ->where('pi.status', 'paid')
            ->whereMonth('pp.period_start', $month)
            ->whereYear('pp.period_start', $year)
            ->select('u.name', 'sp.id as staff_id',
                \Illuminate\Support\Facades\DB::raw('pi.shif_employee as shif_amount'))
            ->get();

        $total = $items->sum('shif_amount');
        if ($request->get('download') === 'csv') {
            return $this->downloadRemittanceCsv($items, $total, $period, 'SHIF', ['Employee','SHIF Amount'], fn($i) => [$i->name, number_format($i->shif_amount,2)], "SHIF_{$year}_{$month}.csv");
        }
        return view('payroll.remittance', compact('items','total','period','month','year') + ['type'=>'SHIF','columns'=>['Employee','SHIF Amount'],'fields'=>['name','shif_amount'],'note'=>'Social Health Insurance Fund — submit via eCitizen by the 9th of the following month.','route'=>'payroll.shif-remittance']);
    }

    private function downloadRemittanceCsv($items, $total, $period, $type, $headers, $rowFn, $filename)
    {
        $httpHeaders = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"$filename\""];
        $callback = function() use ($items, $total, $period, $type, $headers, $rowFn) {
            $out = fopen('php://output', 'w');
            \App\Support\Csv::put($out, [$type . ' Remittance Schedule', $period]);
            \App\Support\Csv::put($out, []);
            \App\Support\Csv::put($out, $headers);
            foreach ($items as $item) \App\Support\Csv::put($out, $rowFn($item));
            \App\Support\Csv::put($out, []);
            \App\Support\Csv::put($out, ['TOTAL', '', '', '', number_format($total, 2)]);
            fclose($out);
        };
        return response()->stream($callback, 200, $httpHeaders);
    }

    // ── HELB Remittance ───────────────────────────────────────────────────────

    public function helbRemittance(Request $request)
    {
        $businessId = Auth::user()->currentBusiness()->id;
        $month = (int) $request->get('month', now()->month);
        $year  = (int) $request->get('year', now()->year);

        $items = DB::table('payroll_items as pi')
            ->join('users as u', 'u.id', '=', 'pi.user_id')
            ->join('payroll_periods as pp', 'pp.id', '=', 'pi.payroll_period_id')
            ->leftJoin('staff_profiles as sp', function ($join) {
                $join->on('sp.user_id', '=', 'pi.user_id')
                     ->on('sp.business_id', '=', 'pi.business_id');
            })
            ->where('pi.business_id', $businessId)
            ->where('pi.status', 'paid')
            ->whereMonth('pp.period_start', $month)
            ->whereYear('pp.period_start', $year)
            ->where('pi.helb', '>', 0)
            ->select('u.name', 'sp.helb_account_number', 'pi.helb')
            ->get();

        $total  = $items->sum('helb');
        $period = \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y');

        if ($request->get('download') === 'csv') {
            $filename = "HELB_Remittance_{$year}_{$month}.csv";
            $headers  = [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];
            $callback = function () use ($items, $total) {
                $out = fopen('php://output', 'w');
                \App\Support\Csv::put($out, ['Employee Name', 'HELB Account Number', 'Amount (KSh)']);
                foreach ($items as $item) {
                    \App\Support\Csv::put($out, [$item->name, $item->helb_account_number ?? 'N/A', number_format($item->helb, 2)]);
                }
                \App\Support\Csv::put($out, ['TOTAL', '', number_format($total, 2)]);
                fclose($out);
            };
            return response()->stream($callback, 200, $headers);
        }

        return view('payroll.helb-remittance', compact('items', 'total', 'period', 'month', 'year'));
    }
}
