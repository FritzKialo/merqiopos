<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model {
    use HasFactory, SoftDeletes, BelongsToBusiness, LogsActivity;

    protected $fillable = [
        'business_id',
        'category_id',
        'name',
        'sku',
        'barcode',
        'barcode_symbology',
        'brand',
        'description',
        'image',
        'buying_price',
        'selling_price',
        'stock_qty',
        'reorder_level',
        'unit',
        'buy_unit',
        'units_per_buy_unit',
        'status',
        'is_featured',
        'hide_in_pos',
        'hide_in_shop',
        'vat_rate',
        'tax_category',
        'etims_item_cls_cd', 'etims_registered_at',
        'has_variants',
        'track_batches',
        'expiry_alert_days',
        'track_serials',
    ];

    protected $casts = [
        'buying_price'          => 'decimal:2',
        'selling_price'         => 'decimal:2',
        'stock_qty'             => 'integer',
        'reorder_level'         => 'integer',
        'units_per_buy_unit'    => 'integer',
        'low_stock_alert_sent_at' => 'datetime',
        'vat_rate'              => 'decimal:2',
        'has_variants'          => 'boolean',
        'track_batches'         => 'boolean',
        'track_serials'         => 'boolean',
        'is_featured'           => 'boolean',
        'hide_in_pos'           => 'boolean',
        'hide_in_shop'          => 'boolean',
    ];

    // Product belongs to a category
    public function category() {
        return $this->belongsTo(Category::class);
    }

    // Gallery images (the main `image` column is separate — the first
    // photo shown, gallery is everything additional)
    public function images() {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    // Suppliers this product is sourced from, with per-supplier part
    // number/price on the pivot record.
    public function productSuppliers() {
        return $this->hasMany(ProductSupplier::class);
    }

    public function suppliers() {
        return $this->belongsToMany(Supplier::class, 'product_suppliers')
            ->withPivot(['supplier_part_no', 'supplier_price'])
            ->withTimestamps();
    }

    // Main product image URL, or null if none uploaded — callers decide
    // their own placeholder (see shop/index.blade.php's tile fallback).
    // Verifies the file is actually on disk, not just that the column is
    // set — an unexplained stale/broken path turned up on an existing
    // product during testing (file 404s, cause unconfirmed), which would
    // otherwise render as a blank broken-image box on the shop instead of
    // falling back to the placeholder tile.
    public function imageUrl(): ?string {
        if (!$this->image) return null;
        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($this->image)) return null;
        return asset('storage/' . $this->image);
    }

    // Variants
    public function variants() {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function activeVariants() {
        return $this->variants()->where('is_active', true);
    }

    // Price tiers
    public function priceTiers() {
        return $this->hasMany(ProductPriceTier::class);
    }

    public function priceForTier(?int $tierId): float {
        if (!$tierId) return (float) $this->selling_price;
        $tierPrice = $this->priceTiers()->where('price_tier_id', $tierId)->first();
        return $tierPrice ? (float) $tierPrice->price : (float) $this->selling_price;
    }

    // Check if stock is low
    public function isLowStock(): bool {
        return $this->stock_qty <= $this->reorder_level;
    }

    // Check if out of stock
    public function isOutOfStock(): bool {
        return $this->stock_qty <= 0;
    }

    // Calculate profit margin
    public function profitMargin(): float {
        if ($this->buying_price <= 0) return 0;
        return round(
            (($this->selling_price - $this->buying_price)
            / $this->buying_price) * 100, 2
        );
    }

    // Calculate profit per unit
    public function profitPerUnit(): float {
        return $this->selling_price - $this->buying_price;
    }

    // Stock value at buying price
    public function stockValue(): float {
        return $this->stock_qty * $this->buying_price;
    }

    // Scope: only active products
    public function scopeActive($query) {
        return $query->where('status', 'active');
    }

    // Scope: only low stock products.
    // reorder_level = 0 is the established "reorder tracking disabled for
    // this product" sentinel (ReorderCheck/CheckNotifications both already
    // guard on 'reorder_level > 0' before using it) — without that same
    // guard here, any product with tracking explicitly turned off would
    // still show up as "low stock" everywhere the moment its stock hit
    // zero, across all 6 places this shared scope is used: the dashboard
    // report, the inventory page's Low Stock filter (x3), the public API,
    // and the low-stock email alerts.
    public function scopeLowStock($query) {
        return $query->where('reorder_level', '>', 0)
            ->whereColumn(
                'stock_qty', '<=', 'reorder_level'
            );
    }

    /**
     * Effective VAT rate: product-level > business-level > 0
     */
    public function effectiveVatRate(\App\Models\Business $business): float
    {
        if (! is_null($this->vat_rate)) {
            return (float) $this->vat_rate;
        }
        return $business->vatRate();
    }

    /**
     * Which tax_rules category this product's line items fall into —
     * 'standard' unless the merchant has explicitly set it to something
     * else (reduced/zero_rated/exempt) via Settings > Tax Rules.
     */
    public function effectiveTaxCategory(): string
    {
        return $this->tax_category ?? 'standard';
    }

    // Auto generate SKU if not provided
    public static function generateSku(
        string $name, 
        int $businessId
    ): string {
        $prefix = strtoupper(substr(
            preg_replace('/[^a-zA-Z]/', '', $name), 
            0, 3
        ));
        $count  = self::where('business_id', $businessId)
                    ->count() + 1;
        return $prefix . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}