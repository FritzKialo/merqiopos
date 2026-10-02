<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeaveTypeController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    public function index() {
        $leaveTypes = LeaveType::forBusiness($this->businessId())
            ->orderBy('name')
            ->get();

        return view('settings.leave-types', compact('leaveTypes'));
    }

    public function store(Request $request) {
        $data = $this->validated($request);
        $data['business_id'] = $this->businessId();

        LeaveType::create($data);

        return back()->with('success', "Leave type \"{$data['name']}\" added.");
    }

    public function update(Request $request, LeaveType $leaveType) {
        abort_if($leaveType->business_id !== $this->businessId(), 403);

        $leaveType->update($this->validated($request));

        return back()->with('success', "\"{$leaveType->name}\" updated.");
    }

    public function destroy(LeaveType $leaveType) {
        abort_if($leaveType->business_id !== $this->businessId(), 403);

        // Deleting cascades to leave_requests + leave_balances, which would erase
        // history. Block it once the type has been used.
        if ($leaveType->leaveRequests()->exists()) {
            return back()->with('error',
                "Cannot delete \"{$leaveType->name}\" — staff have already requested it. "
                . "Deleting it would erase that leave history.");
        }

        $name = $leaveType->name;
        $leaveType->delete();

        return back()->with('success', "Leave type \"{$name}\" removed.");
    }

    private function validated(Request $request): array {
        $request->validate([
            'name'          => 'required|string|max:100',
            'days_per_year' => 'required|integer|min:0|max:365',
        ]);

        return [
            'name'              => $request->name,
            'days_per_year'     => $request->days_per_year,
            'is_paid'           => $request->boolean('is_paid'),
            'requires_approval' => $request->boolean('requires_approval'),
        ];
    }
}
