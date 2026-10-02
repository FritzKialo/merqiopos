<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Sale;
use App\Services\EtimsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SubmitEtimsDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $type,
        public readonly int $id
    ) {}

    public function handle(): void
    {
        if ($this->type === 'refund') {
            $refund = \App\Models\EtimsRefund::find($this->id);
            if (! $refund || $refund->status === 'submitted') return;

            $business = \App\Models\Business::find($refund->business_id);
            $service  = $business ? EtimsService::forBusiness($business) : null;
            if (! $service || ! $service->isConfigured()) return;

            // The original sale/invoice may still be waiting in the queue —
            // a refund can't reference a receipt KRA hasn't got yet. Try
            // again shortly instead of failing.
            $original = $refund->sale_id ? Sale::find($refund->sale_id) : Invoice::find($refund->invoice_id);
            if ($original && $original->etims_status === 'pending' && $this->attempts() < $this->tries) {
                $this->release(120);
                return;
            }

            $service->submitRefund($refund);
            return;
        }

        if ($this->type === 'invoice') {
            $model = Invoice::find($this->id);
            if (! $model) return;

            $model->load('business');
            $service = EtimsService::forBusiness($model->business);
            if (! $service->isConfigured()) return;

            $service->submitInvoice($model);

        } elseif ($this->type === 'sale') {
            $model = Sale::find($this->id);
            if (! $model) return;

            $model->load('business');
            $service = EtimsService::forBusiness($model->business);
            if (! $service->isConfigured()) return;

            $service->submitSale($model);
        }
    }
}
