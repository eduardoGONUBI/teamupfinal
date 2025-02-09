<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventUser extends Model
{
    use HasFactory;

    protected $table = 'event_user';

    protected $fillable = [
        'event_id',
        'event_name',
        'user_id',
        'user_name',
        'message',
    ];
}
