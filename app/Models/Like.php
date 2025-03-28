<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Like extends Model
{
    use HasFactory;

    protected $fillable = ['liker_id', 'liker_type', 'post_id'];

    /**
     * العلاقة مع المنشور
     */
    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * العلاقة مع المعجب (يمكن أن يكون مستخدمًا أو خبيرًا)
     */
    public function liker()
    {
        return $this->morphTo();
    }
}
