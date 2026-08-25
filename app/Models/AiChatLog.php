<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiChatLog extends Model
{
    protected $fillable = [
        'user_id',
        'message',
        'response',
        'source',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}