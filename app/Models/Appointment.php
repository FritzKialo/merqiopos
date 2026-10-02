<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id', 'customer_id', 'user_id', 'service_id', 'booked_by',
        'appointment_date', 'start_time', 'end_time', 'status',
        'notes', 'total_price', 'reminder_sent',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'total_price'      => 'decimal:2',
        'reminder_sent'    => 'boolean',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class); // staff
    }

    public function bookedBy()
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where('business_id', $businessId);
    }
}
