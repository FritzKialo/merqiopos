<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CashRegister extends Model {
    protected $fillable = ['business_id','user_id','opened_at','closed_at','opening_float','expected_closing','actual_closing','difference','status','notes'];
    protected $casts = ['opened_at' => 'datetime', 'closed_at' => 'datetime'];
    public function user() { return $this->belongsTo(User::class); }
    public function entries() { return $this->hasMany(CashRegisterEntry::class); }
    public function scopeForBusiness($q, $id = null) { return $q->where('business_id', $id ?? Auth::user()->currentBusiness()->id); }
    public function isOpen(): bool { return $this->status === 'open'; }
    public function calculateExpected(): float {
        $in = $this->entries->whereIn('entry_type', ['sale','float_add'])->sum('amount');
        $out = $this->entries->whereIn('entry_type', ['refund','expense','float_remove'])->sum('amount');
        return $this->opening_float + $in - $out;
    }
}
