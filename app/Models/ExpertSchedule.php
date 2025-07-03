<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpertSchedule extends Model
{
    use HasFactory;

    // جدول قاعدة البيانات الذي يرتبط به الموديل
    protected $table = 'schedules';

    // حقل البيانات التي يمكن ملؤها بشكل جماعي
    protected $fillable = [
        'expert_id',
        'day',
        'start',
        'end',
    ];

    // العلاقة بين الخبير و الجدول الحالي


    public function expert()
    {
        return $this->belongsTo(Expert::class);
    }
}
