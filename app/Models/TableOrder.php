<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TableOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id', 'restaurant_table_id', 'user_id', 'customer_id',
        'status', 'notes', 'opened_at', 'closed_at', 'subtotal', 'total',
        'customer_name', 'sale_id', 'amount_tendered', 'payment_method', 'billed_at',
        'service_charge_percent', 'service_charge_amount', 'merged_into_id',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'billed_at' => 'datetime',
        'amount_tendered' => 'decimal:2',
        'subtotal'  => 'decimal:2',
        'total'     => 'decimal:2',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function table()
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(TableOrderItem::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'table_order_id');
    }

    /** The order this one's items were folded into, when its table was merged into another. */
    public function mergedInto()
    {
        return $this->belongsTo(TableOrder::class, 'merged_into_id');
    }

    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    /** Items still to be paid for (not cancelled, not already on a paid sale). */
    public function outstandingItems()
    {
        return $this->items()->where('status', '!=', 'cancelled')->whereNull('sale_id');
    }

    /** Service charge for a given amount at this order's rate. */
    public function serviceChargeOn(float $amount): float
    {
        return round($amount * (float) $this->service_charge_percent / 100, 2);
    }

    /**
     * subtotal/total describe what is STILL TO PAY: items already settled by a
     * split payment no longer count, and the service charge is added on top.
     */
    public function recalculate(): void
    {
        $subtotal = (float) $this->outstandingItems()->sum('total');
        $service  = $this->serviceChargeOn($subtotal);
        $this->update([
            'subtotal'              => $subtotal,
            'service_charge_amount' => $service,
            'total'                 => round($subtotal + $service, 2),
        ]);
    }
}
