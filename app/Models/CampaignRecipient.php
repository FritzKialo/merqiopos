<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CampaignRecipient extends Model {
    protected $fillable = ['campaign_id','customer_id','phone','email','status','error_message','sent_at'];
    protected $casts = ['sent_at' => 'datetime'];
    public function campaign() { return $this->belongsTo(Campaign::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
}
