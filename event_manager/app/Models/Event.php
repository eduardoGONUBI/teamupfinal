<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'sport_id',
        'date',
        'place',
        'user_id',
        'user_name',
        'status',
        'max_participants',
        'weather',
    ];
    

 
    public function participants()
    {
        return $this->hasMany(Participant::class, 'event_id', 'id');
    }
    
    

    /**
     * Relationship: Event creator.
     *
     * Defines a one-to-many relationship between the user who created the event and the event.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function sport() 
    {
        return $this->belongsTo(Sport::class);
    }
}
