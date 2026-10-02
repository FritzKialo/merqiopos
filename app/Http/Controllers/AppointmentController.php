<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    private function business()
    {
        return Auth::user()->currentBusiness();
    }

    private function businessId(): int
    {
        return $this->business()->id;
    }

    private function authorize(Appointment $appointment): void
    {
        abort_if($appointment->business_id !== $this->businessId(), 403);
    }

    public function index(Request $request)
    {
        $businessId = $this->businessId();
        $date = $request->input('date', now()->toDateString());
        $staffId = $request->input('staff_id');

        $query = Appointment::forBusiness($businessId)
            ->with(['customer', 'user', 'service'])
            ->whereDate('appointment_date', $date)
            ->orderBy('start_time');

        if ($staffId) {
            $query->where('user_id', $staffId);
        }

        $appointments = $query->get();

        $scheduled  = $appointments->where('status', 'scheduled')->count();
        $confirmed  = $appointments->where('status', 'confirmed')->count();
        $completed  = $appointments->where('status', 'completed')->count();

        $staff = User::whereHas('businesses', fn($q) => $q->where('businesses.id', $businessId))
            ->orWhere(function($q) use ($businessId) {
                $q->whereHas('organization', fn($o) => $o->whereHas('businesses', fn($b) => $b->where('businesses.id', $businessId)));
            })
            ->orderBy('name')->get();

        return view('appointments.index', compact(
            'appointments', 'date', 'staffId', 'staff',
            'scheduled', 'confirmed', 'completed'
        ));
    }

    public function create(Request $request)
    {
        $businessId = $this->businessId();
        $services   = Service::forBusiness($businessId)->active()->orderBy('name')->get();
        $customers  = Customer::where('business_id', $businessId)->orderBy('name')->get();
        $staff      = User::whereHas('businesses', fn($q) => $q->where('businesses.id', $businessId))
            ->orderBy('name')->get();

        return view('appointments.create', compact('services', 'customers', 'staff'));
    }

    public function store(Request $request)
    {
        $businessId = $this->businessId();

        // service_id/customer_id were unscoped exists: checks — either could
        // link this appointment to another business's service or customer.
        $request->validate([
            'service_id'       => ['required', 'integer', \Illuminate\Validation\Rule::exists('services', 'id')->where('business_id', $businessId)],
            'customer_id'      => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('customers', 'id')->where('business_id', $businessId)],
            // Unscoped exists: previously — could assign a completely
            // unrelated user (any business, or none) as the appointment's
            // staff member. Checked against the business_user pivot, the
            // same source StaffController uses to determine who actually
            // belongs to this business.
            'user_id'          => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('business_user', 'user_id')->where('business_id', $businessId)],
            'appointment_date' => 'required|date',
            'start_time'       => 'required|date_format:H:i',
            'end_time'         => 'required|date_format:H:i|after:start_time',
            'notes'            => 'nullable|string|max:2000',
            'total_price'      => 'required|numeric|min:0',
        ]);

        // Double-booking check
        if ($request->user_id) {
            $conflict = Appointment::forBusiness($businessId)
                ->where('user_id', $request->user_id)
                ->whereDate('appointment_date', $request->appointment_date)
                ->where('status', '!=', 'cancelled')
                ->where(function ($q) use ($request) {
                    $q->where(function ($inner) use ($request) {
                        $inner->where('start_time', '<', $request->end_time)
                              ->where('end_time', '>', $request->start_time);
                    });
                })->exists();

            if ($conflict) {
                return back()->withErrors(['start_time' => 'This staff member has a conflicting appointment at that time.'])->withInput();
            }
        }

        $appointment = Appointment::create([
            'business_id'      => $businessId,
            'service_id'       => $request->service_id,
            'customer_id'      => $request->customer_id,
            'user_id'          => $request->user_id,
            'booked_by'        => Auth::id(),
            'appointment_date' => $request->appointment_date,
            'start_time'       => $request->start_time,
            'end_time'         => $request->end_time,
            'status'           => 'scheduled',
            'notes'            => $request->notes,
            'total_price'      => $request->total_price,
        ]);

        return redirect()->route('appointments.show', $appointment)
            ->with('success', 'Appointment booked successfully.');
    }

    public function show(Appointment $appointment)
    {
        $this->authorize($appointment);
        $appointment->load(['customer', 'user', 'service', 'bookedBy']);
        return view('appointments.show', compact('appointment'));
    }

    public function edit(Appointment $appointment)
    {
        $this->authorize($appointment);
        $businessId = $this->businessId();
        $services   = Service::forBusiness($businessId)->active()->orderBy('name')->get();
        $customers  = Customer::where('business_id', $businessId)->orderBy('name')->get();
        $staff      = User::whereHas('businesses', fn($q) => $q->where('businesses.id', $businessId))
            ->orderBy('name')->get();
        $appointment->load('service');
        return view('appointments.edit', compact('appointment', 'services', 'customers', 'staff'));
    }

    public function update(Request $request, Appointment $appointment)
    {
        $this->authorize($appointment);
        $request->validate([
            'service_id'       => ['required', 'integer', \Illuminate\Validation\Rule::exists('services', 'id')->where('business_id', $appointment->business_id)],
            'customer_id'      => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('customers', 'id')->where('business_id', $appointment->business_id)],
            'user_id'          => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('business_user', 'user_id')->where('business_id', $appointment->business_id)],
            'appointment_date' => 'required|date',
            'start_time'       => 'required|date_format:H:i',
            'end_time'         => 'required|date_format:H:i|after:start_time',
            'notes'            => 'nullable|string|max:2000',
            'total_price'      => 'required|numeric|min:0',
        ]);

        $appointment->update($request->only([
            'service_id','customer_id','user_id',
            'appointment_date','start_time','end_time','notes','total_price',
        ]));

        return redirect()->route('appointments.show', $appointment)
            ->with('success', 'Appointment updated.');
    }

    public function updateStatus(Request $request, Appointment $appointment)
    {
        $this->authorize($appointment);
        $request->validate([
            'status' => 'required|in:scheduled,confirmed,completed,cancelled,no_show',
        ]);
        $appointment->update(['status' => $request->status]);
        return back()->with('success', 'Status updated.');
    }

    public function destroy(Appointment $appointment)
    {
        $this->authorize($appointment);
        $appointment->delete();
        return redirect()->route('appointments.index')
            ->with('success', 'Appointment deleted.');
    }
}
