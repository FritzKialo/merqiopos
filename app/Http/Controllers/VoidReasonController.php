<?php

namespace App\Http\Controllers;

use App\Models\VoidReason;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VoidReasonController extends Controller {

    private function businessId(): int {
        return Auth::user()->currentBusiness()->id;
    }

    public function index() {
        $voidReasons = VoidReason::forBusiness($this->businessId())
            ->orderBy('name')
            ->get();

        return view('settings.void-reasons', compact('voidReasons'));
    }

    public function store(Request $request) {
        $data = $this->validated($request);
        $data['business_id'] = $this->businessId();

        VoidReason::create($data);

        return back()->with('success', "Void reason \"{$data['name']}\" added.");
    }

    public function update(Request $request, VoidReason $voidReason) {
        abort_if($voidReason->business_id !== $this->businessId(), 403);

        $voidReason->update($this->validated($request));

        return back()->with('success', "\"{$voidReason->name}\" updated.");
    }

    public function destroy(VoidReason $voidReason) {
        abort_if($voidReason->business_id !== $this->businessId(), 403);

        // Deleting would null out void_reason_id on every past void_request
        // that used it, erasing part of the audit trail. Block it once used.
        if ($voidReason->voidRequests()->exists()) {
            return back()->with('error',
                "Cannot delete \"{$voidReason->name}\" — it has already been used on a void request. "
                . "Disable it instead so it stops showing as an option.");
        }

        $name = $voidReason->name;
        $voidReason->delete();

        return back()->with('success', "Void reason \"{$name}\" removed.");
    }

    private function validated(Request $request): array {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        return [
            'name'    => $request->name,
            'enabled' => $request->boolean('enabled'),
        ];
    }
}
