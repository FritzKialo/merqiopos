<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model {
    use HasFactory, SoftDeletes, BelongsToBusiness, LogsActivity;

    protected $fillable = [
        'business_id',
        'name',
        'email',
        'phone',
        'address',
        'contact_person',
        'account_number',
        'notes',
        'is_active',
        'payable_balance',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'payable_balance' => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────

    public function purchaseOrders() {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function supplierPayments() {
        return $this->hasMany(SupplierPayment::class);
    }

    public function payableBalance(): float {
        return (float) $this->payable_balance;
    }

    // business() is provided by BelongsToBusiness trait

    // ── Scopes ─────────────────────────────────────

    public function scopeActive($query) {
        return $query->where('is_active', true);
    }
}
