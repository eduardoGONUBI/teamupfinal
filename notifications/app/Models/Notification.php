<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications'; // Specify the correct table name if different

    // Define which columns can be mass-assigned
    protected $fillable = [
        'event_id',
        'event_name',
        'user_id',
        'user_name',
        'message',
    ];
}
