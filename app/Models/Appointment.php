<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{

    use HasFactory;
    protected $fillable = [
        'user_id',
        'expert_id',
        'from',
        'to',
        'deposit_amount',
        'payment_intent_id',
        'status',
        'is_completed',
        'latitude',
        'longitude',
        'is_open'
    ];


    public function rate()
    {
        return $this->hasOne(Rate::class);
    }
    public function ratings()
    {
        return $this->hasMany(Rate::class, 'appointment_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    // App\Models\Appointment.php

    public function expert()
    {
        return $this->belongsTo(\App\Models\Expert::class, 'expert_id');
    }


}
