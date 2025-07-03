<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Message extends Model
{
    use HasFactory;

    protected $table = "messages";
    protected $fillable = [
        'sender_id',
        'sender_type',
        'receiver_id',
        'receiver_type',
        'chat_id',
        'message',
    ];

    // كل رسالة تابعة لمحادثة معينة
    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class, 'chat_id');
    }

    // معرفة المرسل سواء كان User أو Expert
    public function sender(): MorphTo
    {
        return $this->morphTo();
    }

    // معرفة المستلم سواء كان User أو Expert
    public function receiver(): MorphTo
    {
        return $this->morphTo();
    }
}
