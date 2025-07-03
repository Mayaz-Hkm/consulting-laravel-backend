<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'expert_id',
        'isAvailable',
        'day',
        'start',
        'end',
    ];

    protected $casts = [
        'isAvailable' => 'boolean',
        'start' => 'datetime:H:i',
        'end' => 'datetime:H:i',
    ];

    // علاقة مع الخبير
    public function expert()
    {
        return $this->belongsTo(Expert::class);
    }

    // نطاق لجلب المواعيد المتاحة فقط
    public function scopeAvailable($query)
    {
        return $query->where('isAvailable', true);
    }
}
