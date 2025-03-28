<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;


class Comment extends Model
{
    use HasFactory;
    protected $fillable = [
        'userable_id',
        'userable_type',
        'post_id',
        'body',
        'parent_id',
    ];


    // علاقة مع المنشور (Post)
    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function userable()
    {
        return $this->morphTo(); // هذه هي العلاقة polymorphic
    }
// في نموذج Comment
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // علاقة مع التعليقات الردود (Replies)
    public function replies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    // علاقة مع التعليق الرئيسي (Parent Comment)
    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }
}
