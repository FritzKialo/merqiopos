<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
class Campaign extends Model {
    protected $fillable = ['business_id','name','channel','segment_type','segment_config','message_template','status','recipient_count','sent_count','failed_count','scheduled_at','sent_at'];
    protected $casts = ['segment_config' => 'array', 'scheduled_at' => 'datetime', 'sent_at' => 'datetime'];
    public function recipients() { return $this->hasMany(CampaignRecipient::class); }
    public function scopeForBusiness($q, $id = null) { return $q->where('business_id', $id ?? Auth::user()->currentBusiness()->id); }
}
