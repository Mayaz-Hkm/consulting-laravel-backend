<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rate extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $fillable = ['appointment_id','expert_id', 'user_id', 'starsNumber','comment'];

    public function expert()
    {
        return $this->belongsTo(Expert::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

}
