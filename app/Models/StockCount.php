<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class StockCount extends Model {
    protected $fillable = ['business_id','reference','status','counted_by','notes','started_at','completed_at'];
    protected $casts = ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    public function business() { return $this->belongsTo(Business::class); }
    public function user() { return $this->belongsTo(User::class, 'counted_by'); }
    public function items() { return $this->hasMany(StockCountItem::class); }
    public function scopeForBusiness($query, $id = null) {
        return $query->where('business_id', $id ?? Auth::user()->currentBusiness()->id);
    }
    public function isEditable(): bool { return in_array($this->status, ['draft','in_progress']); }
}
