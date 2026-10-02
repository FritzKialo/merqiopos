<?php

namespace App\Services;

use App\Mail\SaleReceiptMail;
use App\Models\Sale;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class ReceiptService
{
    /**
     * Send a paid receipt to the customer via WhatsApp and/or email.
     *
     * WhatsApp goes to the phone that actually paid (e.g. the M-Pesa number
     * captured on the transaction) when provided, otherwise the customer's
     * saved phone. Email goes to the customer's saved email. Both are attempted
     * independently; whichever channel is configured + has a destination is used.
     *
     * @return array{whatsapp: bool, email: bool}
     */
    public static function send(Sale $sale, ?string $phoneOverride = null): array
    {
        $sale->loadMissing(['customer', 'business']);
        $business = $sale->business;
        $results  = ['whatsapp' => false, 'email' => false];

        // A signed, login-free link to the printable receipt.
        $link = URL::signedRoute('receipt.public', ['sale' => $sale->id]);

        // ── WhatsApp ── to the paying phone, else the customer's phone
        $phone = $phoneOverride ?: $sale->customer?->phone;
        if ($phone && $business && $business->whatsapp_enabled) {
            $wa = WhatsAppService::forBusiness($business);
            if ($wa->isConfigured()) {
                $results['whatsapp'] = $wa->sendReceipt($phone, $sale, $link);
            }
        }

        // ── Email ── to the customer's email
        if ($sale->customer?->email) {
            try {
                Mail::to($sale->customer->email)->send(new SaleReceiptMail($sale, $link));
                $results['email'] = true;
            } catch (\Throwable $e) {
                // Was Log::warning() — invisible in production, whose LOG_LEVEL
                // only records error and above, which is exactly why this
                // failure had no trace anywhere despite happening on every
                // send. Bumped to error() so the real cause actually shows
                // up in storage/logs/laravel.log.
                Log::error('ReceiptService: email failed', [
                    'sale_id' => $sale->id,
                    'customer_email' => $sale->customer->email,
                    'error'   => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                ]);
            }
        }

        return $results;
    }
}
