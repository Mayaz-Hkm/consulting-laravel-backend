<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostInterest extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id', 'expert_id'
    ];

    // علاقة مع المنشور (Post)
    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    // علاقة مع الخبير (User)
    public function expert()
    {
        return $this->belongsTo(User::class, 'expert_id');
    }
}
