<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramToken extends Model
{
    use HasFactory;
    protected $table = 'telegram_tokens';
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'user_name',
        'chat_id',
        'token'
    ];
}
