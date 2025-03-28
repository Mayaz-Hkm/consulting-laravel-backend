<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'category_id',
        'section_id',
        'images',
    ];

    protected $casts = [
        'images' => 'array', // تخزين الصور كمصفوفة JSON
    ];

    /**
     * العلاقة بين المنشور والمستخدم (كل منشور ينتمي لمستخدم واحد).
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * العلاقة بين المنشور والتعليقات (كل منشور لديه عدة تعليقات).
     */
    public function comments()
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id')->orderBy('created_at', 'asc');
    }


    /**
     * العلاقة بين المنشور والإعجابات (كل منشور يمكن أن يحصل على عدة إعجابات).
     */
    public function likes()
    {
        return $this->morphMany(Like::class, 'liker');
    }

}
