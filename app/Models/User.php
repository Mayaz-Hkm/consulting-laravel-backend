<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable
{ use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [ 'userName',
        'email',
        'password',
        'timezone',
        'mobile'];

    protected $hidden = [
        'password',
        'remember_token',
        ];

    protected $casts = [ 'email_verified_at' => 'datetime', ];


    public function comments()
    {
        return $this->morphMany(Comment::class, 'userable');
    }

    public function likedPosts()
    {
        return $this->morphMany(Like::class, 'liker');
    }
    public function ratings()
    {
        return $this->hasMany(Rate::class, 'user_id');
    }

}
