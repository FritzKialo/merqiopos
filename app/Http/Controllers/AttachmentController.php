<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AttachmentController extends Controller
{
    // attachable_type -> model class. Every entry here MUST have a
    // business_id column, since store() below trusts this map to verify the
    // referenced record actually belongs to the current business before
    // attaching a file to it (previously unvalidated — a user could point
    // attachable_id at another business's record).
    private const ATTACHABLE_TYPES = [
        'Invoice'           => \App\Models\Invoice::class,
        'ProformaInvoice'   => \App\Models\ProformaInvoice::class,
        'Quote'             => \App\Models\Quote::class,
        'Expense'           => \App\Models\Expense::class,
        'PurchaseOrder'     => \App\Models\PurchaseOrder::class,
        'CreditNote'        => \App\Models\CreditNote::class,
        'Sale'              => \App\Models\Sale::class,
        'Asset'             => \App\Models\BusinessAsset::class,
        'Loan'              => \App\Models\BusinessLoan::class,
        'RecurringInvoice'  => \App\Models\RecurringInvoice::class,
        'DeliveryNote'      => \App\Models\DeliveryNote::class,
        'SupplierCreditNote' => \App\Models\SupplierCreditNote::class,
    ];

    public function store(Request $request)
    {
        $businessId = Auth::user()->currentBusiness()->id;

        $validator = Validator::make($request->all(), [
            'file'            => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,xlsx,xls,csv,doc,docx',
            'attachable_type' => ['required', 'string', \Illuminate\Validation\Rule::in(array_keys(self::ATTACHABLE_TYPES))],
            'attachable_id'   => 'required|integer',
        ]);
        $validator->after(function ($validator) use ($request, $businessId) {
            $type = $request->attachable_type;
            if (! isset(self::ATTACHABLE_TYPES[$type])) {
                return; // already flagged by the Rule::in check above
            }
            $modelClass = self::ATTACHABLE_TYPES[$type];
            $exists = $modelClass::where('id', $request->attachable_id)
                ->where('business_id', $businessId)
                ->exists();
            if (! $exists) {
                $validator->errors()->add('attachable_id', 'The selected record does not exist for this business.');
            }
        });
        $validator->validate();

        $file = $request->file('file');
        $path = $file->store('attachments/' . Auth::user()->currentBusiness()->id, 'local');

        $attachment = Attachment::create([
            'business_id'    => Auth::user()->currentBusiness()->id,
            'user_id'        => Auth::id(),
            'attachable_type' => $request->attachable_type,
            'attachable_id'  => $request->attachable_id,
            'original_name'  => $file->getClientOriginalName(),
            'file_path'      => $path,
            'mime_type'      => $file->getMimeType(),
            'file_size'      => $file->getSize(),
        ]);

        return response()->json([
            'id'   => $attachment->id,
            'name' => $attachment->original_name,
            'size' => $attachment->size_human,
            'url'  => route('attachments.download', $attachment),
        ]);
    }

    public function download(Attachment $attachment)
    {
        if ($attachment->business_id !== Auth::user()->currentBusiness()->id) {
            abort(403);
        }
        return Storage::disk('local')->download($attachment->file_path, $attachment->original_name);
    }

    public function destroy(Attachment $attachment)
    {
        if ($attachment->business_id !== Auth::user()->currentBusiness()->id) {
            abort(403);
        }
        Storage::disk('local')->delete($attachment->file_path);
        $attachment->delete();
        return response()->json(['success' => true]);
    }
}
