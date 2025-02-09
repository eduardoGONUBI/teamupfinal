<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Disable timestamps.
     */
    public $timestamps = false;

    /**
     * Relationship: Events the user has joined.
     *
     * Defines a many-to-many relationship between users and events.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function joinedEvents()
    {
        return $this->belongsToMany(Event::class, 'event_user')
                    ->withTimestamps(); // Tracks when the user joined the event
    }

    /**
     * Relationship: Events created by the user.
     *
     * Defines a one-to-many relationship between the user and the events they have created.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function createdEvents()
    {
        return $this->hasMany(Event::class, 'user_id');
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key-value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'is_admin' => $this->is_admin,
        ];
    }
}
