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
        'is_open',
    ];


    public function rate()
    {
        return $this->hasOne(Rate::class);
    }

}
