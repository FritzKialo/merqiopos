<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CashRegisterEntry extends Model {
    protected $fillable = ['cash_register_id','entry_type','amount','description','reference'];
    public function register() { return $this->belongsTo(CashRegister::class, 'cash_register_id'); }
}
