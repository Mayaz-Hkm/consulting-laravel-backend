<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
protected $fillable = ['sectionName', 'category_id'];

public function category()
{
return $this->belongsTo(Category::class);
}

// العلاقة: التخصص مرتبط بالعديد من الخبراء
public function experts()
{
return $this->hasMany(Expert::class);
}
}
