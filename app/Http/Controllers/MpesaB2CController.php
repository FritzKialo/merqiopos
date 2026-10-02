<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MpesaB2CController extends Controller
{
    /**
     * Safaricom calls this URL when a B2C payment result is available.
     * Always returns 200 — Safaricom retries if it gets anything else.
     */
    public function result(Request $request): Response
    {
        $body   = $request->json()->all();
        $result = $body['Result'] ?? [];

        $conversationId = $result['ConversationID'] ?? null;
        $resultCode     = isset($result['ResultCode']) ? (int) $result['ResultCode'] : null;
        $resultDesc     = $result['ResultDesc'] ?? '';

        Log::info('MpesaB2C: result callback received.', [
            'conversation_id' => $conversationId,
            'result_code'     => $resultCode,
            'result_desc'     => $resultDesc,
        ]);

        // Bumped these from warning() to error() — invisible in production,
        // whose LOG_LEVEL only records error and above (see ReceiptService.php).
        // Every case below is a payroll M-Pesa disbursement result the app
        // failed to apply — staff could go unpaid with the owner never
        // finding out from the app itself.
        if (! $conversationId) {
            Log::error('MpesaB2C: result callback missing ConversationID.');
            return response('ok');
        }

        $item = PayrollItem::where('mpesa_conversation_id', $conversationId)->first();

        if (! $item) {
            Log::error('MpesaB2C: no payroll item matched ConversationID.', [
                'conversation_id' => $conversationId,
            ]);
            return response('ok');
        }

        try {
            DB::transaction(function () use ($item, $resultCode, $resultDesc) {
                if ($resultCode === 0) {
                    // Success — mark paid, close period, post expense
                    $item->update([
                        'status'            => 'paid',
                        'mpesa_result_code' => $resultCode,
                        'mpesa_result_desc' => $resultDesc,
                    ]);

                    $period = $item->period;
                    $closed = $period->checkAndClose();

                    if ($closed) {
                        $period->refresh();
                        $this->postExpense($period);
                    }

                    Log::info('MpesaB2C: payroll item marked paid.', [
                        'item_id'   => $item->id,
                        'period_id' => $item->payroll_period_id,
                        'closed'    => $closed,
                    ]);
                } else {
                    // Failure — mark failed so the owner can retry
                    $item->update([
                        'status'            => 'failed',
                        'mpesa_result_code' => $resultCode,
                        'mpesa_result_desc' => $resultDesc,
                    ]);

                    Log::error('MpesaB2C: payment failed.', [
                        'item_id'     => $item->id,
                        'result_code' => $resultCode,
                        'result_desc' => $resultDesc,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::error('MpesaB2C: exception while processing result.', [
                'error'           => $e->getMessage(),
                'conversation_id' => $conversationId,
            ]);
        }

        return response('ok');
    }

    /**
     * Safaricom calls this URL when a B2C request times out on their end.
     */
    public function timeout(Request $request): Response
    {
        $body           = $request->json()->all();
        $conversationId = $body['Result']['ConversationID']
            ?? $body['ConversationID']
            ?? null;

        // Bumped from warning() — see the note above on this controller's
        // other callback method.
        Log::error('MpesaB2C: timeout callback received.', [
            'conversation_id' => $conversationId,
            'body'            => $body,
        ]);

        if ($conversationId) {
            PayrollItem::where('mpesa_conversation_id', $conversationId)
                ->where('status', 'pending_payment')
                ->update([
                    'status'            => 'failed',
                    'mpesa_result_code' => -1,
                    'mpesa_result_desc' => 'Payment timed out on Safaricom side.',
                ]);
        }

        return response('ok');
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private function postExpense(PayrollPeriod $period): void
    {
        $period->postExpenseOnce();
    }
}
