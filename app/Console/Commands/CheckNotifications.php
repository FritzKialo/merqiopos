<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\AppNotification;
use App\Models\Business;

class CheckNotifications extends Command
{
    protected $signature   = 'notifications:check';
    protected $description = 'Check and create system notifications (low stock, overdue invoices, etc.)';

    public function handle()
    {
        Business::all()->each(function ($business) {
            $ownerId = $business->users()->wherePivot('role', 'owner')->first()?->id;
            if (!$ownerId) return;

            // Low stock check
            $lowStock = DB::table('products')
                ->where('business_id', $business->id)
                ->where('status', 'active')
                ->where('stock_qty', '<=', DB::raw('reorder_level'))
                ->whereNotNull('reorder_level')
                ->where('reorder_level', '>', 0)
                ->get();

            foreach ($lowStock as $p) {
                $exists = AppNotification::where('business_id', $business->id)
                    ->where('type', 'low_stock')
                    ->where('title', 'LIKE', '%' . $p->name . '%')
                    ->where('created_at', '>=', now()->subDay())
                    ->exists();
                if (!$exists) {
                    AppNotification::send(
                        $business->id, $ownerId, 'low_stock',
                        'Low Stock: ' . $p->name,
                        'Stock level (' . $p->stock_qty . ') is at or below reorder level (' . $p->reorder_level . ').',
                        '/inventory', 'package'
                    );
                }
            }

            // Overdue invoices
            $overdueCount = DB::table('invoices')
                ->where('business_id', $business->id)
                ->whereIn('status', ['sent', 'partially_paid'])
                ->where('due_date', '<', now()->toDateString())
                ->count();

            if ($overdueCount > 0) {
                $exists = AppNotification::where('business_id', $business->id)
                    ->where('type', 'overdue_invoice')
                    ->where('created_at', '>=', now()->subDay())
                    ->exists();
                if (!$exists) {
                    AppNotification::send(
                        $business->id, $ownerId, 'overdue_invoice',
                        $overdueCount . ' Overdue Invoice' . ($overdueCount > 1 ? 's' : ''),
                        'You have ' . $overdueCount . ' overdue invoice' . ($overdueCount > 1 ? 's that require' : ' that requires') . ' attention.',
                        '/invoices', 'money'
                    );
                }
            }
        });

        $this->info('Notifications checked.');
    }
}
