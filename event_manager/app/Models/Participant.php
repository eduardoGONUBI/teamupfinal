<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Participant extends Model
{
    protected $table = 'event_user';

    protected $fillable = ['event_id', 'user_id', 'user_name'];

    public $timestamps = true;

    // Define the relationship back to the event
    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
