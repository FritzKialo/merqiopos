<?php

namespace App\Jobs;

use App\Models\Business;
use App\Models\PayrollItem;
use App\Services\MpesaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Pays one payroll item to the employee's phone through M-Pesa B2C.
 *
 * The outcome is NOT known when this job ends: Safaricom accepts the request
 * and reports the result later to /api/mpesa/b2c/result, which marks the item
 * paid or failed (see MpesaB2CController).
 *
 * Before this rewrite the job compared the business's environment to the
 * string 'live' — but businesses store 'sandbox' or 'production' — so the
 * "real payment" branch could never run: every payout was silently treated
 * as a dry run and marked PAID with no money sent. It also sent an empty
 * security credential and a made-up initiator name.
 */
class SendMpesaPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Never retried automatically: a retry after a lost response could pay
    // the same salary twice. A failed item is retried by the owner on purpose.
    public int $tries = 1;

    // The credential arguments below are kept only so payouts already sitting
    // in the queue (serialised with them) still load; they are no longer used —
    // the business's own stored credentials are read at run time.
    public function __construct(
        public readonly int    $payrollItemId,
        public readonly float  $amount,
        public readonly string $phoneNumber,
        public readonly string $shortcode = '',
        public readonly string $consumerKey = '',
        public readonly string $consumerSecret = '',
        public readonly string $environment = 'sandbox',
        public readonly string $reference = '',
        public readonly bool   $dryRun = false,
    ) {}

    public function handle(): void
    {
        $item = PayrollItem::find($this->payrollItemId);

        if (! $item) {
            Log::error('SendMpesaPayment: payroll item not found.', ['id' => $this->payrollItemId]);
            return;
        }

        // Only an item waiting for its payment may be sent — never one already paid.
        if ($item->status !== 'pending_payment') {
            Log::error('SendMpesaPayment: item is not awaiting payment, skipped.', ['id' => $item->id, 'status' => $item->status]);
            return;
        }

        $phone = $this->normalisePhone($this->phoneNumber);
        if (! $phone) {
            Log::error('SendMpesaPayment: invalid phone number.', ['raw_phone' => $this->phoneNumber, 'reference' => $this->reference]);
            $item->update(['status' => 'failed', 'mpesa_result_desc' => 'Invalid phone number format.']);
            return;
        }

        // Local development / explicit dry run only: pretend it worked.
        if ($this->dryRun || app()->environment('local', 'testing')) {
            Log::info('SendMpesaPayment: dry run — no money sent.', ['reference' => $this->reference]);
            $this->markPaidAndCloseIfDone($item, 0, 'Dry-run success.');
            return;
        }

        $business = Business::find($item->business_id);
        $ready = $business
            && $business->hasMpesaConfigured()
            && ! empty($business->mpesa_initiator_name)
            && ! empty($business->mpesa_security_credential);

        if (! $ready) {
            $item->update([
                'status'            => 'failed',
                'mpesa_result_desc' => 'M-Pesa payouts are not set up. Add the Daraja initiator name and security credential in Settings → M-Pesa, or pay this employee another way and mark them paid.',
            ]);
            return;
        }

        try {
            $ack = (new MpesaService($business->mpesaCredentials()))->b2cPayment(
                $phone,
                (float) $item->net_pay,
                $business->mpesa_initiator_name,
                Crypt::decryptString($business->mpesa_security_credential),
                'Payroll ' . $this->reference,
                $this->reference ?: ('PAYROLL-' . $item->id),
                url('/api/mpesa/b2c/result'),
                url('/api/mpesa/b2c/timeout')
            );

            if ($ack['accepted']) {
                // Stays 'pending_payment' until Safaricom's result callback.
                $item->update(['mpesa_conversation_id' => $ack['conversationId']]);
                Log::info('SendMpesaPayment: B2C request accepted.', ['reference' => $this->reference, 'conversation_id' => $ack['conversationId']]);
            } else {
                Log::error('SendMpesaPayment: B2C request rejected.', ['reference' => $this->reference, 'message' => $ack['message']]);
                $item->update(['status' => 'failed', 'mpesa_result_desc' => mb_substr('Safaricom rejected the payout: ' . $ack['message'], 0, 500)]);
            }
        } catch (\Throwable $e) {
            Log::error('SendMpesaPayment: exception.', ['reference' => $this->reference, 'error' => $e->getMessage()]);
            $item->update(['status' => 'failed', 'mpesa_result_desc' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Mark the item paid, close the period if it was the last one, and post the expense. */
    private function markPaidAndCloseIfDone(PayrollItem $item, int $resultCode, string $resultDesc): void
    {
        $item->update([
            'status'            => 'paid',
            'mpesa_result_code' => $resultCode,
            'mpesa_result_desc' => $resultDesc,
        ]);

        $period = $item->period;
        if ($period->checkAndClose()) {
            $period->refresh();
            $period->postExpenseOnce();
        }
    }

    private function normalisePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '254' . substr($digits, 1);
        }
        if (str_starts_with($digits, '254') && strlen($digits) === 12) {
            return $digits;
        }
        if (str_starts_with($digits, '7') && strlen($digits) === 9) {
            return '254' . $digits;
        }
        if (str_starts_with($digits, '1') && strlen($digits) === 9) {
            return '254' . $digits;
        }

        return null;
    }
}
