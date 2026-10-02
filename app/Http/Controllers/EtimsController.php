<?php

namespace App\Http\Controllers;

use App\Jobs\SubmitEtimsDocument;
use App\Models\Invoice;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EtimsController extends Controller
{
    public function resubmit(Request $request)
    {
        $request->validate([
            'type' => 'required|in:invoice,sale,refund',
            'id'   => 'required|integer',
        ]);

        $businessId = Auth::user()->currentBusiness()->id;

        if ($request->type === 'refund') {
            $refund = \App\Models\EtimsRefund::where('id', $request->id)
                ->where('business_id', $businessId)->firstOrFail();

            if ($refund->status === 'submitted') {
                return back()->with('error', 'This refund was already accepted by eTIMS.');
            }

            $refund->update(['status' => 'pending', 'message' => null]);
            SubmitEtimsDocument::dispatch('refund', $refund->id);

            return back()->with('success', 'Refund resubmission queued. Status will update shortly.');
        }

        if ($request->type === 'invoice') {
            $model = Invoice::where('id', $request->id)
                ->where('business_id', $businessId)
                ->firstOrFail();
        } else {
            $model = Sale::where('id', $request->id)
                ->where('business_id', $businessId)
                ->firstOrFail();
        }

        if ($model->etims_status === 'submitted') {
            return back()->with('error', 'This document was already accepted by eTIMS — resubmitting would register it with KRA twice.');
        }

        $model->update(['etims_status' => 'pending']);

        SubmitEtimsDocument::dispatch($request->type, $model->id);

        return back()->with('success', 'Resubmission queued. Status will update shortly.');
    }
}
