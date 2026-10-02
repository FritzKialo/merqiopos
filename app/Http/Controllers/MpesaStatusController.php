<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\MpesaStatusCheck;
use App\Models\Sale;
use App\Services\MpesaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Confirms a typed-in M-Pesa receipt code with Safaricom's Transaction Status
 * service. Start a check from a sale ("Verify with Safaricom"); Safaricom
 * answers asynchronously on result().
 */
class MpesaStatusController extends Controller
{
    /** Can this business run checks? (needs a Daraja initiator + security credential) */
    public static function isAvailable(Business $business): bool
    {
        return $business->hasMpesaConfigured()
            && !empty($business->mpesa_initiator_name)
            && !empty($business->mpesa_security_credential);
    }

    /** Start (or restart) a check for the M-Pesa code recorded on a sale. */
    public static function start(Sale $sale, string $receipt, float $amount): MpesaStatusCheck
    {
        $business = $sale->business;

        $check = MpesaStatusCheck::create([
            'business_id'    => $business->id,
            'sale_id'        => $sale->id,
            'receipt'        => strtoupper(trim($receipt)),
            'claimed_amount' => $amount,
            'status'         => 'pending',
        ]);

        try {
            $credential = Crypt::decryptString($business->mpesa_security_credential);
            $ack = (new MpesaService($business->mpesaCredentials()))->queryTransactionStatus(
                $check->receipt,
                $business->mpesa_initiator_name,
                $credential,
                url('/api/mpesa/status/result'),
                url('/api/mpesa/status/timeout'),
                'Verify ' . $sale->invoice_number
            );

            if ($ack['accepted']) {
                $check->update(['conversation_id' => $ack['conversationId']]);
            } else {
                $check->update(['status' => 'failed', 'result_desc' => mb_substr($ack['message'], 0, 500), 'resolved_at' => now()]);
            }
        } catch (\Throwable $e) {
            Log::error('M-Pesa Transaction Status request failed', ['sale_id' => $sale->id, 'error' => $e->getMessage()]);
            $check->update(['status' => 'failed', 'result_desc' => mb_substr($e->getMessage(), 0, 500), 'resolved_at' => now()]);
        }

        return $check;
    }

    /** POST /sales/{sale}/verify-mpesa — the "Verify with Safaricom" button. */
    public function verify(Request $request, Sale $sale)
    {
        abort_if($sale->business_id !== Auth::user()->currentBusiness()->id, 403);
        $business = $sale->business;

        if (! self::isAvailable($business)) {
            return back()->with('error', 'Add your Daraja initiator name and security credential in Settings → M-Pesa first.');
        }
        if (empty($sale->mpesa_reference)) {
            return back()->with('error', 'This sale has no M-Pesa code to verify.');
        }

        $latest = MpesaStatusCheck::latestForSale($sale->id);
        if ($latest && $latest->status === 'pending' && $latest->created_at->gt(now()->subMinutes(2))) {
            return back()->with('info', 'A check is already in progress — refresh in a moment.');
        }

        $check = self::start($sale, $sale->mpesa_reference, (float) $sale->paid_amount);

        return back()->with(
            $check->status === 'failed' ? 'error' : 'success',
            $check->status === 'failed'
                ? 'Safaricom did not accept the request: ' . $check->result_desc
                : 'Asked Safaricom to confirm ' . $check->receipt . '. The result appears on this sale in a moment.'
        );
    }

    /** Safaricom posts the answer here. Always acknowledges. */
    public function result(Request $request)
    {
        $result = $request->json('Result') ?? $request->input('Result') ?? [];
        $conversationId = $result['ConversationID'] ?? null;

        Log::info('M-Pesa Transaction Status result', ['conversation_id' => $conversationId, 'code' => $result['ResultCode'] ?? null]);

        $check = $conversationId ? MpesaStatusCheck::where('conversation_id', $conversationId)->first() : null;
        if (! $check) {
            Log::error('M-Pesa Transaction Status result matched no check', ['conversation_id' => $conversationId]);
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        if (in_array($check->status, ['confirmed', 'mismatch', 'not_found'], true)) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $code = (int) ($result['ResultCode'] ?? 1);
        $desc = (string) ($result['ResultDesc'] ?? '');
        $params = collect($result['ResultParameters']['ResultParameter'] ?? [])->pluck('Value', 'Key');

        if ($code !== 0) {
            // A receipt Safaricom has no record of is a "not found", anything
            // else (bad credentials, permissions) is a failure of the check.
            $notFound = stripos($desc, 'not found') !== false || stripos($desc, 'invalid transaction') !== false || stripos($desc, 'does not exist') !== false;
            $check->update([
                'status'      => $notFound ? 'not_found' : 'failed',
                'result_desc' => mb_substr($desc, 0, 500),
                'response'    => $result,
                'resolved_at' => now(),
            ]);
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $completed = strcasecmp((string) ($params['TransactionStatus'] ?? ''), 'Completed') === 0;
        $amount    = isset($params['Amount']) ? (float) $params['Amount'] : null;

        if (! $completed) {
            $status = 'failed';
        } elseif ($amount !== null && $amount + 0.009 < (float) $check->claimed_amount) {
            $status = 'mismatch';
        } else {
            $status = 'confirmed';
        }

        $check->update([
            'status'           => $status,
            'result_desc'      => $status === 'mismatch'
                ? 'Safaricom shows KSh ' . number_format($amount, 2) . ' for this code, less than the KSh ' . number_format($check->claimed_amount, 2) . ' recorded.'
                : mb_substr($desc, 0, 500),
            'confirmed_amount' => $amount,
            'response'         => $result,
            'resolved_at'      => now(),
        ]);

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    public function timeout(Request $request)
    {
        $conversationId = $request->json('Result.ConversationID');
        if ($conversationId) {
            MpesaStatusCheck::where('conversation_id', $conversationId)->where('status', 'pending')
                ->update(['status' => 'failed', 'result_desc' => 'Safaricom timed out — try again.', 'resolved_at' => now()]);
        }
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
